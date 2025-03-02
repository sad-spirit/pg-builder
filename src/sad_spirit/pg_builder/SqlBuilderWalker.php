<?php

/*
 * This file is part of sad_spirit/pg_builder:
 * query builder for Postgres backed by SQL parser
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder;

/**
 * A tree walker that generates SQL from abstract syntax tree
 */
class SqlBuilderWalker implements StatementToStringWalker
{
    /** Current indentation level */
    protected int $indentLevel = 0;

    /**
     * Options, mostly deal with prettifying output
     * @var array<string, mixed>
     */
    protected array $options = [
        'indent'         => "    ",
        'linebreak'      => "\n",
        'wrap'           => 120,
        'escape_unicode' => false
    ];

    /** Dummy typecast expression used for checks with argumentNeedsParentheses() */
    private readonly nodes\expressions\TypecastExpression $dummyTypecast;

    /**
     * Identifiers are likely to appear in output more than once, so we cache the result of their escaping
     * @var string[]
     */
    private array $escapedUnicodeIdentifiers = [];

    /**
     * Whether to generate SQL compatible with PDO::prepare()
     */
    private bool $PDOPrepareCompatibility = false;

    /**
     * Constructor, accepts options that tune output generation
     *
     * Known options:
     *  - 'indent':         string used to indent statements
     *  - 'linebreak':      string used as a line break
     *  - 'wrap':           builder will try to wrap long lists of items before lines get that long
     *  - 'escape_unicode': if set to true, non-ASCII characters in string constants and identifiers
     *                      will be represented by Unicode escape sequences
     *
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        $this->options = \array_merge($this->options, $options);

        $this->dummyTypecast = new nodes\expressions\TypecastExpression(
            new nodes\expressions\StringConstant('dummy'),
            new nodes\TypeName(new nodes\QualifiedName('dummy'))
        );
    }

    public function enablePDOPrepareCompatibility(bool $enable): void
    {
        $this->PDOPrepareCompatibility = $enable;
    }

    /**
     * Checks whether a given SELECT node contains any of ORDER BY / LIMIT / OFFSET / locking clauses
     *
     * Nodes with these clauses should be always wrapped in parentheses when used in
     * set operations, as per spec these clauses apply to a result of set operation rather
     * than to its operands
     *
     * @param SelectCommon $statement
     * @return bool
     */
    protected function containsCommonClauses(SelectCommon $statement): bool
    {
        return 0 < \count($statement->order)
               || 0 < \count($statement->locking)
               || null !== $statement->limit
               || null !== $statement->offset;
    }

    /**
     * Checks whether an argument of expression should be parenthesized
     */
    protected function argumentNeedsParentheses(
        nodes\ScalarExpression $argument,
        nodes\ScalarExpression $expression,
        bool $right = false
    ): bool {
        $argumentPrecedence   = $argument->getPrecedence()->value;
        $expressionPrecedence = $expression->getPrecedence()->value;

        if ($expression instanceof nodes\expressions\BetweenExpression) {
            // to be on a safe side, wrap just about everything in parentheses, it is quite
            // difficult to distinguish between a_expr and b_expr at this stage
            return $argumentPrecedence
                < ($right ? enums\ScalarExpressionPrecedence::TYPECAST->value : $expressionPrecedence);

        } elseif ($expression instanceof nodes\Indirection) {
            if ($expression[0] instanceof nodes\ArrayIndexes) {
                return $argumentPrecedence < enums\ScalarExpressionPrecedence::ATOM->value;
            } else {
                return !($argument instanceof nodes\expressions\Parameter
                         || $argument instanceof nodes\expressions\SubselectExpression
                            && !$argument->operator);
            }
        }

        return match ($expression->getAssociativity()) {
            enums\ScalarExpressionAssociativity::RIGHT => $argumentPrecedence < $expressionPrecedence
               || !$right && $argumentPrecedence === $expressionPrecedence,
            enums\ScalarExpressionAssociativity::LEFT => $argumentPrecedence < $expressionPrecedence
               || $right && $argumentPrecedence === $expressionPrecedence,
            default => $argumentPrecedence <= $expressionPrecedence
        };
    }

    /**
     * Adds parentheses around argument if its precedence is lower than that of parent expression
     */
    protected function optionalParentheses(
        nodes\ScalarExpression $argument,
        nodes\ScalarExpression $expression,
        bool $right = false
    ): string {
        $needParens = $this->argumentNeedsParentheses($argument, $expression, $right);

        return ($needParens ? '(' : '') . $argument->dispatch($this) . ($needParens ? ')' : '');
    }

    /**
     * Returns the string to indent the current expression
     */
    protected function getIndent(): string
    {
        return \str_repeat((string) $this->options['indent'], $this->indentLevel);
    }

    /**
     * Joins the array elements into a string using given separator, adding line breaks and indents where needed
     *
     * If the builder was configured with 'wrap' and 'linebreak' options, the method will try to insert
     * line breaks between list items to keep the created lines' length below 'wrap' items. It will add a
     * proper indent after a line break.
     *
     * The parts are checked for existing linebreaks so that strings containing them (e.g. subselects)
     * will be added properly.
     *
     * @param string   $lead      Leading keywords for expression list, e.g. 'select ' or 'order by '
     * @param string[] $parts     Array of expressions
     * @param string   $separator String to use for separating expressions
     * @return string
     */
    protected function implode(string $lead, array $parts, string $separator = ','): string
    {
        if (0 === \count($parts)) {
            return $lead;

        } elseif (!$this->options['linebreak'] || !$this->options['wrap']) {
            return $lead . \implode($separator . ' ', $parts);
        }

        $lineSep   = $separator . $this->options['linebreak'] . $this->getIndent();
        $indentLen = \strlen($this->getIndent());
        $string    = $lead . \array_shift($parts);
        $lineLen   = (false === $lastBreak = \strrpos($string, (string) $this->options['linebreak']))
                     ? \strlen($string) : \strlen($string) - $lastBreak;
        $sepLen    = \strlen($separator) + 1;
        foreach ($parts as $part) {
            $partLen = \strlen($part);
            if (false !== ($lastBreak = \strrpos($part, (string) $this->options['linebreak']))) {
                $firstBreak = \strpos($part, (string) $this->options['linebreak']) ?: $lastBreak;
                if ($lineLen + $firstBreak < $this->options['wrap']) {
                    $string .= $separator . ' ' . $part;
                } else {
                    $string .= $lineSep . $part;
                }
                $lineLen = $partLen - $lastBreak;

            } elseif ($lineLen + $partLen < $this->options['wrap']) {
                $string  .= $separator . ' ' . $part;
                $lineLen += $partLen + $sepLen;

            } else {
                $string  .= $lineSep . $part;
                $lineLen  = $indentLen + $partLen;
            }
        }
        return $string;
    }

    /**
     * Adds string representations of clauses defined in SelectCommon to an array
     *
     * @param string[] $clauses
     */
    protected function addCommonSelectClauses(array &$clauses, SelectCommon $statement): void
    {
        $indent = $this->getIndent();
        $this->indentLevel++;
        if (0 < \count($statement->order)) {
            $clauses[] = $this->implode($indent . 'order by ', $statement->order->dispatch($this), ',');
        }
        if (null !== $statement->limit) {
            if (!$statement->limitWithTies) {
                $clauses[] = $indent . 'limit ' . $statement->limit->dispatch($this);
            } else {
                $parentheses = $statement->limit->getPrecedence()->value < enums\ScalarExpressionPrecedence::ATOM->value;
                $clauses[]   = $indent . 'fetch first '
                               . ($parentheses ? '(' : '')
                               . $statement->limit->dispatch($this)
                               . ($parentheses ? ')' : '')
                               . ' rows with ties';
            }
        }
        if (null !== $statement->offset) {
            $clauses[] = $indent . 'offset ' . $statement->offset->dispatch($this);
        }
        if (0 < \count($statement->locking)) {
            $clauses[] = $this->implode($indent, $statement->locking->dispatch($this), '');
        }
        $this->indentLevel--;
    }

    public function walkSelectStatement(Select $statement): string
    {
        $clauses = [];
        if (0 < \count($statement->with)) {
            $clauses[] = $statement->with->dispatch($this);
        }

        $indent  = $this->getIndent();
        $list    = $indent . 'select ';
        $this->indentLevel++;
        if (true === $statement->distinct) {
            $list .= 'distinct ';
        } elseif ($statement->distinct instanceof nodes\lists\ExpressionList) {
            $list .= $this->implode('distinct on (', $statement->distinct->dispatch($this), ',') . ') ';
        }
        $clauses[] = $this->implode($list, $statement->list->dispatch($this), ',');

        if (0 < \count($statement->from)) {
            $clauses[] = $this->implode($indent . 'from ', $statement->from->dispatch($this), ',');
        }
        if (null !== $statement->where->condition) {
            $clauses[] = $indent . 'where ' . $statement->where->dispatch($this);
        }
        if (0 < \count($statement->group)) {
            $clauses[] = $this->implode($indent . 'group by ', $statement->group->dispatch($this), ',');
        }
        if (null !== $statement->having->condition) {
            $clauses[] = $indent . 'having ' . $statement->having->dispatch($this);
        }
        if (0 < \count($statement->window)) {
            $clauses[] = $this->implode($indent . 'window ', $statement->window->dispatch($this), ',');
        }
        $this->indentLevel--;

        $this->addCommonSelectClauses($clauses, $statement);

        return \implode($this->options['linebreak'] ?: ' ', $clauses);
    }

    public function walkSetOpSelectStatement(SetOpSelect $statement): string
    {
        $indent = $this->getIndent();
        $parts  = [];

        if (0 < \count($statement->with)) {
            $parts[] = $statement->with->dispatch($this);
        }

        if (
            $this->containsCommonClauses($statement->left)
            || $statement->left->getPrecedence()->value < $statement->getPrecedence()->value
        ) {
            $this->indentLevel++;
            $part = $indent . '(' . $this->options['linebreak'] . $statement->left->dispatch($this);
            $this->indentLevel--;
            $parts[] = $part . $this->options['linebreak'] . $indent . ')';

        } else {
            $parts[] = $statement->left->dispatch($this);
        }

        $parts[] = $indent . $statement->operator->value;

        if (
            $this->containsCommonClauses($statement->right)
            || $statement->right->getPrecedence()->value <= $statement->getPrecedence()->value
        ) {
            $this->indentLevel++;
            $part = $indent . '(' . $this->options['linebreak'] . $statement->right->dispatch($this);
            $this->indentLevel--;
            $parts[] = $part . $this->options['linebreak'] . $indent . ')';

        } else {
            $parts[] = $statement->right->dispatch($this);
        }

        $this->addCommonSelectClauses($parts, $statement);

        return \implode($this->options['linebreak'] ?: ' ', $parts);
    }

    public function walkValuesStatement(Values $statement): string
    {
        $sql  = $this->getIndent() . 'values' . ($this->options['linebreak'] ?: ' ');
        $this->indentLevel++;
        $rows = $statement->rows->dispatch($this);
        $this->indentLevel--;

        $parts = [$sql . \implode(',' . ($this->options['linebreak'] ?: ' '), $rows)];

        $this->addCommonSelectClauses($parts, $statement);

        return \implode($this->options['linebreak'] ?: ' ', $parts);
    }

    public function walkDeleteStatement(Delete $statement): string
    {
        $clauses = [];
        if (0 < \count($statement->with)) {
            $clauses[] = $statement->with->dispatch($this);
        }
        $indent = $this->getIndent();
        $this->indentLevel++;
        /** @noinspection SqlWithoutWhere, SqlNoDataSourceInspection */
        $clauses[] = $indent . 'delete from ' . $statement->relation->dispatch($this);

        if (0 < \count($statement->using)) {
            $clauses[] = $this->implode($indent . 'using ', $statement->using->dispatch($this), ',');
        }
        if (null !== $statement->where->condition) {
            $clauses[] = $indent . 'where ' . $statement->where->dispatch($this);
        }
        if (0 < \count($statement->returning)) {
            $clauses[] = $this->implode($indent . 'returning ', $statement->returning->dispatch($this), ',');
        }
        $this->indentLevel--;

        return \implode($this->options['linebreak'] ?: ' ', $clauses);
    }

    public function walkInsertStatement(Insert $statement): string
    {
        $clauses = [];
        if (0 < \count($statement->with)) {
            $clauses[] = $statement->with->dispatch($this);
        }

        $indent = $this->getIndent();
        $this->indentLevel++;

        /** @noinspection SqlNoDataSourceInspection */
        $clauses[] = $indent . 'insert into ' . $statement->relation->dispatch($this);
        if (0 < \count($statement->cols)) {
            $clauses[] = $this->implode($this->getIndent() . '(', $statement->cols->dispatch($this), ',') . ')';
        }
        if (null === $statement->values) {
            $clauses[] = $indent . 'default values';
        } else {
            if (null !== $statement->overriding) {
                $clauses[] = $indent . 'overriding ' . $statement->overriding->value . ' value';
            }
            $this->indentLevel--;
            $clauses[] = $statement->values->dispatch($this);
            $this->indentLevel++;
        }
        if (null !== $statement->onConflict) {
            $clauses[] = $indent . 'on conflict ' . $statement->onConflict->dispatch($this);
        }
        if (0 < \count($statement->returning)) {
            $clauses[] = $this->implode($indent . 'returning ', $statement->returning->dispatch($this), ',');
        }
        $this->indentLevel--;

        return \implode($this->options['linebreak'] ?: ' ', $clauses);
    }

    public function walkUpdateStatement(Update $statement): string
    {
        $clauses = [];
        if (0 < \count($statement->with)) {
            $clauses[] = $statement->with->dispatch($this);
        }

        $indent = $this->getIndent();
        $this->indentLevel++;

        $clauses[] = $indent . 'update ' . $statement->relation->dispatch($this);
        $clauses[] = $this->implode($indent . 'set ', $statement->set->dispatch($this), ',');
        if (0 < \count($statement->from)) {
            $clauses[] = $this->implode($indent . 'from ', $statement->from->dispatch($this), ',');
        }
        if (null !== $statement->where->condition) {
            $clauses[] = $indent . 'where ' . $statement->where->dispatch($this);
        }
        if (0 < \count($statement->returning)) {
            $clauses[] = $this->implode($indent . 'returning ', $statement->returning->dispatch($this), ',');
        }
        $this->indentLevel--;

        return \implode($this->options['linebreak'] ?: ' ', $clauses);
    }

    public function walkArrayIndexes(nodes\ArrayIndexes $node): string
    {
        return '['
               . (null !== $node->lower ? $node->lower->dispatch($this) : '')
               . ($node->isSlice ? ' : ' : '')
               . (null !== $node->upper ? $node->upper->dispatch($this) : '')
               . ']';
    }

    public function walkColumnReference(nodes\ColumnReference $node): string
    {
        if (!$this->options['escape_unicode']) {
            return $node->__toString();
        }

        return (null !== $node->catalog ? $node->catalog->dispatch($this) . '.' : '')
               . (null !== $node->schema ? $node->schema->dispatch($this) . '.' : '')
               . (null !== $node->relation ? $node->relation->dispatch($this) . '.' : '')
               . $node->column->dispatch($this);
    }

    public function walkCommonTableExpression(nodes\CommonTableExpression $node): string
    {
        $this->indentLevel++;
        if (null === $node->materialized) {
            $materialized = '';
        } else {
            $materialized = ($node->materialized ? '' : 'not ') . 'materialized ';
        }
        $sql = $node->alias->dispatch($this) . ' '
               . (
                   0 < \count($node->columnAliases)
                   ? '(' . \implode(', ', $node->columnAliases->dispatch($this)) . ') '
                   : ''
               )
               . 'as ' . $materialized . '(' . $this->options['linebreak'] . $node->statement->dispatch($this);
        $this->indentLevel--;

        $sql .= $this->options['linebreak'] . $this->getIndent() . ')';

        $trailing = [];
        if (null !== $node->search) {
            $trailing[] = $node->search->dispatch($this);
        }
        if (null !== $node->cycle) {
            $trailing[] = $node->cycle->dispatch($this);
        }
        if ([] !== $trailing) {
            $sql .= ' ' . \implode($this->options['linebreak'] . $this->getIndent(), $trailing);
        }

        return $sql;
    }

    public function walkKeywordConstant(nodes\expressions\KeywordConstant $node): string
    {
        return $node->value;
    }

    public function walkNumericConstant(nodes\expressions\NumericConstant $node): string
    {
        return $node->value;
    }

    public function walkStringConstant(nodes\expressions\StringConstant $node): string
    {
        // binary and hex strings do not require escaping
        if (enums\StringConstantType::CHARACTER !== $node->type) {
            return "{$node->type->value}'{$node->value}'";
        }

        if ($this->options['escape_unicode'] && \preg_match('/[\\x80-\\xff]/', $node->value)) {
            // We generate e'...' string instead of u&'...' one as the latter may be rejected by server
            // if standard_conforming_strings setting is off
            return "e'"
                . \implode('', \array_map(function (int $codePoint): string {
                    if (0x27 === $codePoint) {
                        return "\\'";
                    } elseif (0x5c === $codePoint) {
                        return '\\\\';
                    } elseif ($codePoint < 0x80) {
                        return \chr($codePoint);
                    } elseif ($codePoint < 0xFFFF) {
                        return \sprintf('\\u%04x', $codePoint);
                    } else {
                        return \sprintf('\\U%08x', $codePoint);
                    }
                }, self::utf8ToCodePoints($node->value)))
                . "'";
        }

        if (!\str_contains($node->value, "'") && !\str_contains($node->value, '\\')) {
            return "'" . $node->value . "'";
        }
        // We generate dollar-quoted strings by default as those are more readable, having no escapes.
        // As PDO::prepare() may fail with these, fall back to generating C-style escapes
        if ($this->PDOPrepareCompatibility) {
            return "e'" . \strtr($node->value, ["'" => "\\'", "\\" => "\\\\"]) . "'";
        }

        if (!\str_contains($node->value . '$', '$$')) {
            return '$$' . $node->value . '$$';
        } else {
            $i = 1;
            while (\str_contains($node->value . '$', '$_' . (string)$i . '$')) {
                $i++;
            }
            return \sprintf('$_%1$d$%2$s$_%1$d$', $i, $node->value);
        }
    }

    public function walkFunctionCall(nodes\FunctionCall $node): string
    {
        $arguments = (array)$node->arguments->dispatch($this);
        if ($node->variadic) {
            $arguments[] = 'variadic ' . \array_pop($arguments);
        }
        return $node->name->dispatch($this) . '('
               . ($node->distinct ? 'distinct ' : '')
               . \implode(', ', $arguments)
               . (0 < \count($node->order) ? ' order by ' . \implode(',', $node->order->dispatch($this)) : '')
               . ')';
    }

    public function walkSQLValueFunction(nodes\expressions\SQLValueFunction $node): string
    {
        return $node->name->value . (null === $node->modifier ? '' : '(' . $node->modifier->dispatch($this) . ')');
    }

    public function walkSystemFunctionCall(nodes\expressions\SystemFunctionCall $node): string
    {
        return $node->name->value . '(' . \implode(', ', (array)$node->arguments->dispatch($this)) . ')';
    }

    public function walkIdentifier(nodes\Identifier $node): string
    {
        if (!$this->options['escape_unicode'] || !\preg_match('/[\\x80-\\xff]/', $node->value)) {
            return $node->__toString();
        }

        if (!isset($this->escapedUnicodeIdentifiers[$node->value])) {
            $this->escapedUnicodeIdentifiers[$node->value] = 'u&"'
                . \implode('', \array_map(function (int $codePoint): string {
                    if (0x5c === $codePoint) {
                        return '\\\\';
                    } elseif (0x22 === $codePoint) {
                        return '""';
                    } elseif ($codePoint < 0x80) {
                        return \chr($codePoint);
                    } elseif ($codePoint < 0xFFFF) {
                        return \sprintf('\\%04x', $codePoint);
                    } else {
                        return \sprintf('\\+%06x', $codePoint);
                    }
                }, self::utf8ToCodePoints($node->value)))
                . '"';
        }

        return $this->escapedUnicodeIdentifiers[$node->value];
    }

    public function walkIndirection(nodes\Indirection $node): string
    {
        $sql = $this->optionalParentheses($node->expression, $node, false);
        /* @var Node $item */
        foreach ($node as $item) {
            if ($item instanceof nodes\ArrayIndexes) {
                $sql .= $item->dispatch($this);
            } else {
                $sql .= '.' . $item->dispatch($this);
            }
        }

        return $sql;
    }

    public function walkLockingElement(nodes\LockingElement $node): string
    {
        $sql = 'for ' . $node->strength->value;
        if (0 < \count($node)) {
            $sql .= ' of ' . \implode(', ', $this->walkGenericNodeList($node));
        }
        if ($node->noWait) {
            $sql .= ' nowait';
        } elseif ($node->skipLocked) {
            $sql .= ' skip locked';
        }
        return $sql;
    }

    public function walkOrderByElement(nodes\OrderByElement $node): string
    {
        $sql = $node->expression->dispatch($this);
        if (null !== $node->direction) {
            $sql .= ' ' . $node->direction->value;
            if (enums\OrderByDirection::USING === $node->direction) {
                $sql .= ' '  . (
                    $node->operator instanceof nodes\QualifiedOperator
                            ? $node->operator->dispatch($this)
                            : $node->operator
                );
            }
        }
        if (null !== $node->nullsOrder) {
            $sql .= ' nulls ' . $node->nullsOrder->value;
        }
        return $sql;
    }

    public function walkNamedParameter(nodes\expressions\NamedParameter $node): string
    {
        return ':' . $node->name;
    }

    public function walkPositionalParameter(nodes\expressions\PositionalParameter $node): string
    {
        return '$' . (string)$node->position;
    }

    public function walkQualifiedName(nodes\QualifiedName $node): string
    {
        if (!$this->options['escape_unicode']) {
            return $node->__toString();
        }
        return (null !== $node->catalog ? $node->catalog->dispatch($this) . '.' : '')
               . (null !== $node->schema ? $node->schema->dispatch($this) . '.' : '')
               . $node->relation->dispatch($this);
    }

    public function walkQualifiedOperator(nodes\QualifiedOperator $node): string
    {
        if (!$this->options['escape_unicode']) {
            return $node->__toString();
        }
        return 'operator('
               . (null !== $node->catalog ? $node->catalog->dispatch($this) . '.' : '')
               . (null !== $node->schema ? $node->schema->dispatch($this) . '.' : '')
               . ($this->PDOPrepareCompatibility ? \strtr($node->operator, ['?' => '??']) : $node->operator) . ')';
    }

    public function walkSetTargetElement(nodes\SetTargetElement $node): string
    {
        $sql = $node->name->dispatch($this);
        /* @var Node $item */
        foreach ($node as $item) {
            $sql .= ($item instanceof nodes\ArrayIndexes ? '' : '.') . $item->dispatch($this);
        }
        return $sql;
    }

    public function walkSingleSetClause(nodes\SingleSetClause $node): string
    {
        return $node->column->dispatch($this) . ' = ' . $node->value->dispatch($this);
    }

    public function walkMultipleSetClause(nodes\MultipleSetClause $node): string
    {
        return '(' . \implode(', ', $node->columns->dispatch($this)) . ') = '
               . $node->value->dispatch($this);
    }

    public function walkSetToDefault(nodes\SetToDefault $node): string
    {
        return 'default';
    }

    public function walkStar(nodes\Star $node): string
    {
        return '*';
    }

    public function walkTargetElement(nodes\TargetElement $node): string
    {
        return $node->expression->dispatch($this)
               . (null !== $node->alias ? ' as ' . $node->alias->dispatch($this) : '');
    }

    public function walkTypeName(nodes\TypeName $node): string
    {
        $sql = ($node->setOf ? 'setof ' : '')
               . (
                   $node instanceof nodes\IntervalTypeName
                    ? 'interval' . (null !== $node->mask ? ' ' . $node->mask->value : '')
                    : $node->name->dispatch($this)
               )
               . (0 < \count($node->modifiers) ? '(' . \implode(', ', $node->modifiers->dispatch($this)) . ')' : '');
        if (0 < \count($node->bounds)) {
            foreach ($node->bounds as $bound) {
                $sql .= '[' . (-1 === $bound ? '' : $bound) . ']';
            }
        }
        return $sql;
    }

    public function walkWhereOrHavingClause(nodes\WhereOrHavingClause $node): string
    {
        return null !== $node->condition ? $node->condition->dispatch($this) : '';
    }

    public function walkWindowDefinition(nodes\WindowDefinition $node): string
    {
        // name should only be set for windows appearing in WINDOW clause
        $sql = null !== $node->name ? $node->name->dispatch($this) . ' as (' : '(';
        $parts = [];
        if (null !== $node->refName) {
            $parts[] = $node->refName->dispatch($this);
        }
        if (0 < \count($node->partition)) {
            $parts[] = 'partition by ' . \implode(', ', $node->partition->dispatch($this));
        }
        if (0 < \count($node->order)) {
            $parts[] = 'order by ' . \implode(', ', $node->order->dispatch($this));
        }
        if (null !== $node->frame) {
            $parts[] = $node->frame->dispatch($this);
        }
        return $sql . \implode(' ', $parts) . ')';
    }

    public function walkWindowFrameClause(nodes\WindowFrameClause $node): string
    {
        return $node->type->value . ' '
               . (
                   null === $node->end
                   ? $node->start->dispatch($this)
                   : 'between ' . $node->start->dispatch($this) . ' and ' . $node->end->dispatch($this)
               )
               . (null === $node->exclusion ? '' : ' exclude ' . $node->exclusion->value);
    }

    public function walkWindowFrameBound(nodes\WindowFrameBound $node): string
    {
        if (null !== $node->value) {
            return $node->value->dispatch($this) . ' ' . $node->direction->value;
        } elseif (enums\WindowFrameDirection::CURRENT_ROW === $node->direction) {
            return $node->direction->value;
        } else {
            return 'unbounded ' . $node->direction->value;
        }
    }

    public function walkWithClause(nodes\WithClause $node): string
    {
        return $this->implode(
            $this->getIndent() . 'with ' . ($node->recursive ? 'recursive ' : ''),
            $this->walkGenericNodeList($node),
            ','
        );
    }

    /**
     * Used by {@see walkArrayExpression} to enable adding of array keyword only to outermost array literal
     *
     * @param nodes\expressions\ArrayExpression $expression
     * @param bool $keyword
     * @return string
     */
    protected function recursiveArrayExpression(
        nodes\expressions\ArrayExpression $expression,
        bool $keyword = true
    ): string {
        $items = [];
        foreach ($expression as $item) {
            if ($item instanceof nodes\expressions\ArrayExpression) {
                $items[] = $this->recursiveArrayExpression($item, false);
            } else {
                /* @var Node $item */
                $items[] = $item->dispatch($this);
            }
        }
        return ($keyword ? 'array' : '') . '[' . \implode(', ', $items) . ']';
    }

    public function walkArrayExpression(nodes\expressions\ArrayExpression $expression): string
    {
        return $this->recursiveArrayExpression($expression, true);
    }

    public function walkArrayComparisonExpression(nodes\expressions\ArrayComparisonExpression $expression): string
    {
        return $expression->keyword->value . '(' . $expression->array->dispatch($this) . ')';
    }

    public function walkAtTimeZoneExpression(nodes\expressions\AtTimeZoneExpression $expression): string
    {
        return $this->optionalParentheses($expression->argument, $expression, false)
               . ' at time zone '
               . $this->optionalParentheses($expression->timeZone, $expression, true);
    }

    public function walkAtLocalExpression(nodes\expressions\AtLocalExpression $expression): string
    {
        return $this->optionalParentheses($expression->argument, $expression, false)
            . ' at local';
    }

    public function walkBetweenExpression(nodes\expressions\BetweenExpression $expression): string
    {
        return $this->optionalParentheses($expression->argument, $expression)
               . ($expression->not ? ' not ' : ' ') . $expression->operator->value . ' '
               . $this->optionalParentheses($expression->left, $expression, true)
               . ' and '
               . $this->optionalParentheses($expression->right, $expression, true);
    }

    public function walkCaseExpression(nodes\expressions\CaseExpression $expression): string
    {
        $clauses = [];
        if (null !== $expression->argument) {
            $clauses[] = $expression->argument->dispatch($this);
        }
        /* @var nodes\expressions\WhenExpression $whenClause */
        foreach ($expression as $whenClause) {
            $clauses[] = 'when ' . $whenClause->when->dispatch($this)
                         . ' then ' . $whenClause->then->dispatch($this);
        }

        if (null !== $expression->else) {
            $clauses[] = 'else ' . $expression->else->dispatch($this);
        }

        return 'case ' . \implode(' ', $clauses) . ' end';
    }

    public function walkCollateExpression(nodes\expressions\CollateExpression $expression): string
    {
        return $this->optionalParentheses($expression->argument, $expression, false)
               . ' collate ' . $expression->collation->dispatch($this);
    }

    public function walkCollationForExpression(nodes\expressions\CollationForExpression $expression): string
    {
        return 'collation for(' . $expression->argument->dispatch($this) . ')';
    }

    public function walkExtractExpression(nodes\expressions\ExtractExpression $expression): string
    {
        $field = $expression->field instanceof enums\ExtractPart
            ? $expression->field->value
            : (new nodes\Identifier($expression->field))->dispatch($this);

        return 'extract(' . $field . ' from ' . $expression->source->dispatch($this) . ')';
    }

    public function walkFunctionExpression(nodes\expressions\FunctionExpression $expression): string
    {
        if (!$expression->withinGroup) {
            $sql = $this->walkFunctionCall($expression);

        } else {
            $arguments = (array)$expression->arguments->dispatch($this);
            if ($expression->variadic) {
                $arguments[] = 'variadic ' . \array_pop($arguments);
            }
            $sql = $expression->name->dispatch($this)
                   . '('
                   . ($expression->distinct ? 'distinct ' : '')
                   . \implode(', ', $arguments)
                   . ')'
                   . ' within group (order by '
                   . \implode(', ', $expression->order->dispatch($this)) . ')';
        }

        return $sql
               . (null === $expression->filter ? '' : ' filter (where ' . $expression->filter->dispatch($this) . ')')
               . (null === $expression->over ? '' : ' over ' . $expression->over->dispatch($this));
    }

    public function walkInExpression(nodes\expressions\InExpression $expression): string
    {
        if ($expression->right instanceof SelectCommon) {
            $this->indentLevel++;
            $right  = '(' . $this->options['linebreak'] . $expression->right->dispatch($this);
            $this->indentLevel--;
            $right .= $this->options['linebreak'] . $this->getIndent() . ')';

        } else {
            $right = '(' . \implode(', ', $expression->right->dispatch($this)) . ')';
        }

        return $this->optionalParentheses($expression->left, $expression, false)
               . ($expression->not ? ' not in ' : ' in ') . $right;
    }

    public function walkIsDistinctFromExpression(nodes\expressions\IsDistinctFromExpression $expression): string
    {
        return $this->optionalParentheses($expression->left, $expression, false)
               . ' is ' . ($expression->not ? 'not ' : '') . 'distinct from '
               . $this->optionalParentheses($expression->right, $expression, true);
    }

    public function walkIsExpression(nodes\expressions\IsExpression $expression): string
    {
        return $this->optionalParentheses($expression->argument, $expression, false)
               . ' is ' . ($expression->not ? 'not ' : '') . $expression->what->value;
    }

    public function walkLogicalExpression(nodes\expressions\LogicalExpression $expression): string
    {
        $parent = $expression;
        do {
            $parent = $parent->getParentNode();
        } while ($parent instanceof nodes\expressions\LogicalExpression);

        $verbose   = $parent instanceof nodes\WhereOrHavingClause;
        $delimiter = $verbose
                     ? ($this->options['linebreak'] ?: ' ') . $this->getIndent() . $expression->operator->value . ' '
                     : ' ' . $expression->operator->value . ' ';

        $items = [];
        /* @var nodes\ScalarExpression $item */
        foreach ($expression as $item) {
            if ($item->getPrecedence()->value >= $expression->getPrecedence()->value) {
                $items[] = $item->dispatch($this);
            } elseif (!$verbose) {
                $items[] = '(' . $item->dispatch($this) . ')';
            } else {
                $this->indentLevel++;
                $nested = '(' . $this->options['linebreak'] . $this->getIndent() . $item->dispatch($this);
                $this->indentLevel--;
                $items[] = $nested . $this->options['linebreak'] . $this->getIndent() . ')';
            }
        }

        return \implode($delimiter, $items);
    }

    public function walkNormalizeExpression(nodes\expressions\NormalizeExpression $expression): string
    {
        return 'normalize(' . $expression->argument->dispatch($this)
            . (null === $expression->form ? '' : ', ' . $expression->form->value) . ')';
    }

    public function walkNotExpression(nodes\expressions\NotExpression $expression): string
    {
        return 'not ' . $this->optionalParentheses($expression->argument, $expression);
    }

    public function walkNullIfExpression(nodes\expressions\NullIfExpression $expression): string
    {
        return 'nullif(' . $expression->first->dispatch($this) . ', ' . $expression->second->dispatch($this) . ')';
    }

    public function walkOperatorExpression(nodes\expressions\OperatorExpression $expression): string
    {
        return (
            null === $expression->left
            ? ''
            : $this->optionalParentheses($expression->left, $expression, false) . ' '
        )
            . (
                $expression->operator instanceof nodes\QualifiedOperator
                ? $expression->operator->dispatch($this)
                : (
                    $this->PDOPrepareCompatibility
                        ? \strtr($expression->operator, ['?' => '??'])
                        : $expression->operator
                )
            )
            . ' ' . $this->optionalParentheses($expression->right, $expression, true);
    }

    public function walkOverlapsExpression(nodes\expressions\OverlapsExpression $expression): string
    {
        // parentheses are not needed since both arguments can be only row literals
        return $expression->left->dispatch($this) . ' overlaps ' . $expression->right->dispatch($this);
    }

    public function walkOverlayExpression(nodes\expressions\OverlayExpression $expression): string
    {
        return 'overlay(' . $expression->string->dispatch($this)
            . ' placing ' . $expression->newSubstring->dispatch($this)
            . ' from ' . $expression->start->dispatch($this)
            . (null === $expression->count ? '' : ' for ' . $expression->count->dispatch($this))
            . ')';
    }

    public function walkPatternMatchingExpression(nodes\expressions\PatternMatchingExpression $expression): string
    {
        return $this->optionalParentheses($expression->argument, $expression, false)
               . ($expression->not ? ' not ' : ' ') . $expression->operator->value . ' '
               . $this->optionalParentheses($expression->pattern, $expression, true)
               . (
                   null !== $expression->escape
                    ? ' escape ' . $this->optionalParentheses($expression->escape, $expression, true)
                    : ''
               );
    }

    public function walkPositionExpression(nodes\expressions\PositionExpression $expression): string
    {
        // Both arguments are b_expr in grammar, our Parser::RestrictedExpression()
        $substring = $this->optionalParentheses($expression->substring, $this->dummyTypecast);
        $string    = $this->optionalParentheses($expression->string, $this->dummyTypecast, true);

        return 'position(' . $substring . ' in ' . $string . ')';
    }

    public function walkRowExpression(nodes\expressions\RowExpression $expression): string
    {
        if ($expression->getParentNode() instanceof nodes\lists\RowList) {
            return $this->implode($this->getIndent() . '(', $this->walkGenericNodeList($expression), ',') . ')';
        } elseif (\count($expression) < 2) {
            return 'row(' . \implode(', ', $this->walkGenericNodeList($expression)) . ')';
        } else {
            return '(' . \implode(', ', $this->walkGenericNodeList($expression)) . ')';
        }
    }

    public function walkSubstringFromExpression(nodes\expressions\SubstringFromExpression $expression): string
    {
        return 'substring(' . $expression->string->dispatch($this)
            . (null === $expression->from ? '' : ' from ' . $expression->from->dispatch($this))
            . (null === $expression->for ? '' : ' for ' . $expression->for->dispatch($this))
            . ')';
    }

    public function walkSubstringSimilarExpression(nodes\expressions\SubstringSimilarExpression $expression): string
    {
        return 'substring(' . $expression->string->dispatch($this)
            . ' similar ' . $expression->pattern->dispatch($this)
            . ' escape ' . $expression->escape->dispatch($this) . ')';
    }

    public function walkSubselectExpression(nodes\expressions\SubselectExpression $expression): string
    {
        $this->indentLevel++;
        $sql = $expression->operator?->value . '(' . $this->options['linebreak']
               . $expression->query->dispatch($this);
        $this->indentLevel--;

        return $sql . $this->options['linebreak'] . $this->getIndent() . ')';
    }

    public function walkTrimExpression(nodes\expressions\TrimExpression $expression): string
    {
        $arguments  = $this->walkGenericNodeList($expression->arguments);
        $from       = \array_shift($arguments);
        $characters = \array_pop($arguments);

        return 'trim(' . $expression->side->value
            . (null !== $characters ? ' ' . $characters : '')
            . (null !== $from ? ' from ' . $from : '')
            . (empty($arguments) ? '' : ', ' . \implode(', ', $arguments))
            . ')';
    }

    public function walkTypecastExpression(nodes\expressions\TypecastExpression $expression): string
    {
        $parent = $expression->getParentNode();

        if ($parent instanceof nodes\range\FunctionCall || $parent instanceof nodes\range\RowsFromElement) {
            // used in FROM, output longer "CAST(... AS ...)" form
            return 'cast(' . $expression->argument->dispatch($this) . ' as '
                   . $expression->type->dispatch($this) . ')';
        } else {
            // used somewhere else, output shorter form
            return $this->optionalParentheses($expression->argument, $expression)
                   . '::' . $expression->type->dispatch($this);
        }
    }

    public function walkConstantTypecastExpression(nodes\expressions\ConstantTypecastExpression $expression): string
    {
        $modifiers = 0 < \count($expression->type->modifiers)
                     ? ' (' . \implode(', ', $expression->type->modifiers->dispatch($this)) . ')'
                     : '';
        return (
            $expression->type instanceof nodes\IntervalTypeName
            ? 'interval' . (null === $expression->type->mask ? $modifiers : '')
            : $expression->type->name->dispatch($this) . $modifiers
        )
            . ' ' . $expression->argument->dispatch($this)
            . (
                $expression->type instanceof nodes\IntervalTypeName && null !== $expression->type->mask
                ? ' ' . $expression->type->mask->value . $modifiers
                : ''
            );
    }

    public function walkGroupingExpression(nodes\expressions\GroupingExpression $expression): string
    {
        return 'grouping(' . \implode(', ', $this->walkGenericNodeList($expression)) . ')';
    }


    /**
     * {@inheritDoc}
     * @return string[]
     */
    public function walkGenericNodeList(\Traversable $list): array
    {
        $items = [];
        /* @var Node $item */
        foreach ($list as $item) {
            $items[] = $item->dispatch($this);
        }
        return $items;
    }

    /**
     * {@inheritDoc}
     * @return string[]
     */
    public function walkFunctionArgumentList(nodes\lists\FunctionArgumentList $list): array
    {
        $items = [];
        /* @var nodes\ScalarExpression $argument */
        foreach ($list as $key => $argument) {
            if (\is_int($key)) {
                $items[] = $argument->dispatch($this);
            } else {
                $items[] = $key . ' := ' . $argument->dispatch($this);
            }
        }
        return $items;
    }

    public function walkColumnDefinition(nodes\range\ColumnDefinition $node): string
    {
        return $node->name->dispatch($this)
               . ' ' . $node->type->dispatch($this)
               . ($node->collation ? ' collate ' . $node->collation->dispatch($this) : '');
    }

    protected function getFromItemAliases(nodes\range\FromElement $rangeItem): string
    {
        if (null === $rangeItem->tableAlias && null === $rangeItem->columnAliases) {
            return '';
        }
        return ' as'
               . (null !== $rangeItem->tableAlias ? ' ' . $rangeItem->tableAlias->dispatch($this) : '')
               . (
                   null !== $rangeItem->columnAliases
                    ? ' (' . \implode(', ', $rangeItem->columnAliases->dispatch($this)) . ')'
                    : ''
               );
    }

    public function walkRangeFunctionCall(nodes\range\FunctionCall $rangeItem): string
    {
        return ($rangeItem->lateral ? 'lateral ' : '') . $rangeItem->function->dispatch($this)
               . ($rangeItem->withOrdinality ? ' with ordinality' : '')
               . $this->getFromItemAliases($rangeItem);
    }

    public function walkRowsFrom(nodes\range\RowsFrom $rangeItem): string
    {
        return ($rangeItem->lateral ? 'lateral ' : '') . 'rows from('
               . \implode(', ', $rangeItem->functions->dispatch($this)) . ')'
               . ($rangeItem->withOrdinality ? ' with ordinality' : '')
               . $this->getFromItemAliases($rangeItem);
    }

    public function walkRowsFromElement(nodes\range\RowsFromElement $node): string
    {
        return $node->function->dispatch($this)
               . (
                   \count($node->columnAliases) > 0
                   ? ' as (' . \implode(', ', $node->columnAliases->dispatch($this)) . ')'
                   : ''
               );
    }

    public function walkJoinExpression(nodes\range\JoinExpression $rangeItem): string
    {
        $sql = $rangeItem->left->dispatch($this)
               . ($rangeItem->natural ? ' natural' : '')
               . ' ' . $rangeItem->type->value . ' join ';

        if ($rangeItem->right instanceof nodes\range\JoinExpression) {
            $sql .= '(' . $rangeItem->right->dispatch($this) . ')';
        } else {
            $sql .= $rangeItem->right->dispatch($this);
        }

        if ($rangeItem->on) {
            $sql .= ' on ' . $rangeItem->on->dispatch($this);
        } elseif ($rangeItem->using) {
            $sql .= ' ' . $rangeItem->using->dispatch($this);
        }

        $alias = $this->getFromItemAliases($rangeItem);
        return '' === $alias ? $sql : '(' . $sql . ')' . $alias;
    }

    public function walkRelationReference(nodes\range\RelationReference $rangeItem): string
    {
        return (false === $rangeItem->inherit ? 'only ' : '')
               . $rangeItem->name->dispatch($this)
               . (true === $rangeItem->inherit ? ' *' : '')
               . $this->getFromItemAliases($rangeItem);
    }

    public function walkRangeSubselect(nodes\range\Subselect $rangeItem): string
    {
        $this->indentLevel++;
        $sql = ($rangeItem->lateral ? 'lateral (' : '(') . $this->options['linebreak']
               . $rangeItem->query->dispatch($this);
        $this->indentLevel--;

        return $sql . $this->options['linebreak'] . $this->getIndent() . ')'
               . $this->getFromItemAliases($rangeItem);
    }

    public function walkInsertTarget(nodes\range\InsertTarget $target): string
    {
        return $target->relation->dispatch($this)
               . (null === $target->alias ? '' : ' as ' . $target->alias->dispatch($this));
    }

    public function walkUpdateOrDeleteTarget(nodes\range\UpdateOrDeleteTarget $target): string
    {
        return (false === $target->inherit ? 'only ' : '')
               . $target->relation->dispatch($this)
               . (true === $target->inherit ? ' *' : '')
               . (null === $target->alias ? '' : ' as ' . $target->alias->dispatch($this));
    }

    public function walkTableSample(nodes\range\TableSample $rangeItem): string
    {
        return $rangeItem->relation->dispatch($this)
               . ' tablesample ' . $rangeItem->method->dispatch($this)
               . ' (' . \implode(', ', $rangeItem->arguments->dispatch($this)) . ')'
               . (
                   null === $rangeItem->repeatable
                   ? ''
                   : ' repeatable(' . $rangeItem->repeatable->dispatch($this) . ')'
               );
    }


    public function walkXmlElement(nodes\xml\XmlElement $xml): string
    {
        $sql = 'xmlelement(name ' . $xml->name->dispatch($this);
        if (0 < \count($xml->attributes)) {
            $sql .= ', xmlattributes(' . \implode(', ', $xml->attributes->dispatch($this)) . ')';
        }
        if (0 < \count($xml->content)) {
            $sql .= ', ' . \implode(', ', $xml->content->dispatch($this));
        }
        return $sql . ')';
    }

    public function walkXmlExists(nodes\xml\XmlExists $xml): string
    {
        return 'xmlexists(' . $this->optionalParentheses($xml->xpath, $this->dummyTypecast)
            . ' passing ' . $this->optionalParentheses($xml->xml, $this->dummyTypecast, true)
            . ')';
    }

    public function walkXmlForest(nodes\xml\XmlForest $xml): string
    {
        return 'xmlforest(' . \implode(', ', $this->walkGenericNodeList($xml)) . ')';
    }

    public function walkXmlParse(nodes\xml\XmlParse $xml): string
    {
        return 'xmlparse(' . $xml->documentOrContent->value . ' ' . $xml->argument->dispatch($this)
               . ($xml->preserveWhitespace ? ' preserve whitespace' : '') . ')';
    }

    public function walkXmlPi(nodes\xml\XmlPi $xml): string
    {
        return 'xmlpi(name ' . $xml->name->dispatch($this)
               . ($xml->content ? ', ' . $xml->content->dispatch($this) : '') . ')';
    }

    public function walkXmlRoot(nodes\xml\XmlRoot $xml): string
    {
        return 'xmlroot(' . $xml->xml->dispatch($this)
               . ', version ' . ($xml->version ? $xml->version->dispatch($this) : 'no value')
               . ($xml->standalone ? ', standalone ' . $xml->standalone->value : '') . ')';
    }

    public function walkXmlSerialize(nodes\xml\XmlSerialize $xml): string
    {
        return 'xmlserialize(' . $xml->documentOrContent->value . ' ' . $xml->argument->dispatch($this)
               . ' as ' . $xml->type->dispatch($this)
               . (null === $xml->indent ? '' : ($xml->indent ? ' indent' : ' no indent')) . ')';
    }

    public function walkXmlTable(nodes\range\XmlTable $table): string
    {
        $this->indentLevel++;
        $lines = [($table->lateral ? 'lateral ' : '') . 'xmltable('];
        if (0 < \count($table->namespaces)) {
            $lines[] = $this->getIndent() . 'xmlnamespaces(';

            $this->indentLevel++;
            $glue = $this->options['linebreak'] ? ',' . $this->options['linebreak'] . $this->getIndent() : ', ';
            $lines[] = $this->getIndent() . \implode($glue, $this->walkGenericNodeList($table->namespaces));
            $this->indentLevel--;

            $lines[] = $this->getIndent() . '),';
        }

        $lines[] = $this->getIndent() . $this->optionalParentheses($table->rowExpression, $this->dummyTypecast, true)
                   . ' passing ' . $this->optionalParentheses($table->documentExpression, $this->dummyTypecast, true);
        $glue    = $this->options['linebreak']
                   ? ',' . $this->options['linebreak'] . $this->getIndent() . '        ' // let's align columns
                   : ', ';
        $lines[] = $this->getIndent() . 'columns ' . \implode($glue, $this->walkGenericNodeList($table->columns));

        $this->indentLevel--;

        return \implode($this->options['linebreak'] ?: ' ', $lines)
               . $this->options['linebreak'] . $this->getIndent() . ')'
               . $this->getFromItemAliases($table);
    }

    public function walkXmlTypedColumnDefinition(nodes\xml\XmlTypedColumnDefinition $column): string
    {
        $sql = $column->name->dispatch($this) . ' ' . $column->type->dispatch($this);
        if (null !== $column->path) {
            $sql .= ' path ' . $this->optionalParentheses($column->path, $this->dummyTypecast, true);
        }
        if (null !== $column->default) {
            $sql .= ' default ' . $this->optionalParentheses($column->default, $this->dummyTypecast, true);
        }
        if (null !== $column->nullable) {
            $sql .= $column->nullable ? ' null' : ' not null';
        }
        return $sql;
    }

    public function walkXmlOrdinalityColumnDefinition(nodes\xml\XmlOrdinalityColumnDefinition $column): string
    {
        return $column->name->dispatch($this) . ' for ordinality';
    }

    public function walkXmlNamespace(nodes\xml\XmlNamespace $ns): string
    {
        $sql = $this->optionalParentheses($ns->value, $this->dummyTypecast, true);

        return null === $ns->alias ? 'default ' . $sql : $sql . ' as ' . $ns->alias->dispatch($this);
    }

    public function walkOnConflictClause(nodes\OnConflictClause $onConflict): string
    {
        $sql = '';
        if (null !== $onConflict->target) {
            if ($onConflict->target instanceof nodes\Identifier) {
                $sql .= 'on constraint ';
            }
            $sql .= $onConflict->target->dispatch($this);
        }
        $sql .= ' do ' . $onConflict->action->value;
        if (enums\OnConflictAction::UPDATE === $onConflict->action) {
            $indent = $this->getIndent();
            $this->indentLevel++;

            $clauses = [''];
            $clauses[] = $this->implode($indent . 'set ', $onConflict->set->dispatch($this), ',');
            if (null !== $onConflict->where->condition) {
                $clauses[] = $indent . 'where ' . $onConflict->where->dispatch($this);
            }

            $this->indentLevel--;

            $sql .= \implode($this->options['linebreak'] ?: ' ', $clauses);
        }
        return $sql;
    }

    public function walkIndexParameters(nodes\IndexParameters $parameters): string
    {
        return '(' . \implode(', ', $this->walkGenericNodeList($parameters)) . ')'
               . (null === $parameters->where->condition ? '' : ' where ' . $parameters->where->dispatch($this));
    }

    public function walkIndexElement(nodes\IndexElement $element): string
    {
        return (
            $element->expression instanceof nodes\Identifier
            ? $element->expression->dispatch($this)
            : '(' . $element->expression->dispatch($this) . ')'
        )
            . (null === $element->collation ? '' : ' collate ' . $element->collation->dispatch($this))
            . (null === $element->opClass ? '' : ' ' . $element->opClass->dispatch($this))
            . (null === $element->direction ? '' : ' ' . $element->direction->value)
            . (null === $element->nullsOrder ? '' : ' nulls ' . $element->nullsOrder->value);
    }


    public function walkEmptyGroupingSet(nodes\group\EmptyGroupingSet $empty): string
    {
        return '()';
    }

    public function walkCubeOrRollupClause(nodes\group\CubeOrRollupClause $clause): string
    {
        return $clause->type->value . '(' . \implode(', ', $this->walkGenericNodeList($clause)) . ')';
    }

    public function walkGroupingSetsClause(nodes\group\GroupingSetsClause $clause): string
    {
        return 'grouping sets(' . \implode(', ', $this->walkGenericNodeList($clause)) . ')';
    }

    public function walkGroupByClause(nodes\group\GroupByClause $clause): array
    {
        $items = $this->walkGenericNodeList($clause);
        if ($clause->distinct && [] !== $items) {
            $items[0] = 'distinct ' . $items[0];
        }
        return $items;
    }

    public function walkSearchClause(nodes\cte\SearchClause $clause): string
    {
        return 'search ' . ($clause->breadthFirst ? 'breadth' : 'depth') . ' first'
            . ' by ' . \implode(', ', $clause->trackColumns->dispatch($this))
            . ' set ' . $clause->sequenceColumn->dispatch($this);
    }

    public function walkCycleClause(nodes\cte\CycleClause $clause): string
    {
        return 'cycle ' . \implode(', ', $clause->trackColumns->dispatch($this))
            . ' set ' . $clause->markColumn->dispatch($this)
            . (
                null === $clause->markValue || null === $clause->markDefault
                ? ''
                : ' to ' . $clause->markValue->dispatch($this) . ' default ' . $clause->markDefault->dispatch($this)
            )
            . ' using ' . $clause->pathColumn->dispatch($this);
    }

    public function walkUsingClause(nodes\range\UsingClause $clause): string
    {
        $items = $this->walkGenericNodeList($clause);
        return 'using (' . \implode(', ', $items) . ')'
               . (null === $clause->alias ? '' : ' as ' . $clause->alias->dispatch($this));
    }

    public function walkMergeStatement(Merge $statement): string
    {
        $clauses = [];
        if (0 < \count($statement->with)) {
            $clauses[] = $statement->with->dispatch($this);
        }

        $indent = $this->getIndent();
        $this->indentLevel++;

        $clauses[] = $indent . 'merge into ' . $statement->relation->dispatch($this);
        $clauses[] = $indent . 'using ' . $statement->using->dispatch($this);
        $clauses[] = $indent . 'on ' . $statement->on->dispatch($this);

        foreach ($statement->when as $when) {
            $clauses[] = $indent . $when->dispatch($this);
        }
        if (0 < \count($statement->returning)) {
            $clauses[] = $this->implode($indent . 'returning ', $statement->returning->dispatch($this), ',');
        }

        $this->indentLevel--;

        return \implode($this->options['linebreak'] ?: ' ', $clauses);
    }

    public function walkMergeDelete(nodes\merge\MergeDelete $clause): string
    {
        return $this->getIndent() . 'delete';
    }

    public function walkMergeInsert(nodes\merge\MergeInsert $clause): string
    {
        if (null === $clause->values) {
            return $this->getIndent() . 'insert default values';
        }

        $lines = [
            (
                0 === \count($clause->cols)
                ? $this->getIndent() . 'insert'
                : $this->implode($this->getIndent() . 'insert (', $clause->cols->dispatch($this)) . ')'
            )
            . (null === $clause->overriding ? '' : ' overriding ' . $clause->overriding->value . ' value')
        ];
        $lines[] = $clause->values->dispatch($this);

        return \implode($this->options['linebreak'] ?: ' ', $lines);
    }

    public function walkMergeUpdate(nodes\merge\MergeUpdate $clause): string
    {
        return $this->implode($this->getIndent() . 'update set ', $clause->set->dispatch($this));
    }

    public function walkMergeValues(nodes\merge\MergeValues $clause): string
    {
        return $this->implode($this->getIndent() . 'values (', $this->walkGenericNodeList($clause)) . ')';
    }

    public function walkMergeWhenMatched(nodes\merge\MergeWhenMatched $clause): string
    {
        $lines = [
            ($clause->matchedBySource ? 'when matched' : 'when not matched by source')
            . (null === $clause->condition ? '' : ' and ' . $clause->condition->dispatch($this))
            . ' then'
        ];
        $lines[] = null === $clause->action
                   ? $this->getIndent() . 'do nothing'
                   : $clause->action->dispatch($this);

        return \implode($this->options['linebreak'] ?: ' ', $lines);
    }

    public function walkMergeWhenNotMatched(nodes\merge\MergeWhenNotMatched $clause): string
    {
        $lines = [
            'when not matched'
            . (null === $clause->condition ? '' : ' and ' . $clause->condition->dispatch($this))
            . ' then'
        ];
        $lines[] = null === $clause->action
                   ? $this->getIndent() . 'do nothing'
                   : $clause->action->dispatch($this);

        return \implode($this->options['linebreak'] ?: ' ', $lines);
    }

    public function walkMergeAction(nodes\expressions\MergeAction $action): mixed
    {
        return 'merge_action()';
    }

    public function walkIsJsonExpression(nodes\expressions\IsJsonExpression $expression): string
    {
        return $this->optionalParentheses($expression->argument, $expression)
               . ' is ' . ($expression->not ? 'not ' : '') . 'json'
               . (null === $expression->type ? '' : ' ' . $expression->type->value)
               . (
                   null === $expression->uniqueKeys
                   ? ''
                   : ($expression->uniqueKeys ? ' with' : ' without') . ' unique keys'
               );
    }

    public function walkJsonFormat(nodes\json\JsonFormat $clause): string
    {
        return 'format json'
               . (null === $clause->encoding ? '' : ' encoding ' . $clause->encoding->value);
    }

    public function walkJsonReturning(nodes\json\JsonReturning $clause): string
    {
        return 'returning ' . $clause->type->dispatch($this)
               . (null === $clause->format ? '' : ' ' . $clause->format->dispatch($this));
    }

    public function walkJsonFormattedValue(nodes\json\JsonFormattedValue $clause): string
    {
        return $clause->expression->dispatch($this)
               . (null === $clause->format ? '' : ' ' . $clause->format->dispatch($this));
    }

    public function walkJsonKeyValue(nodes\json\JsonKeyValue $clause): string
    {
        return $clause->key->dispatch($this) . ' : ' . $clause->value->dispatch($this);
    }

    protected function walkCommonJsonAggregateFields(nodes\json\JsonAggregate $expression): string
    {
        return (null === $expression->returning ? '' : ' ' . $expression->returning->dispatch($this))
               . ')'
               . (null === $expression->filter ? '' : ' filter (where ' . $expression->filter->dispatch($this) . ')')
               . (null === $expression->over ? '' : ' over ' . $expression->over->dispatch($this));
    }

    protected function jsonOnNullClause(?bool $absentOnNull): string
    {
        if (null === $absentOnNull) {
            return '';
        } else {
            return ($absentOnNull ? ' absent' : ' null') . ' on null';
        }
    }

    protected function jsonUniqueKeysClause(?bool $uniqueKeys): string
    {
        if (null === $uniqueKeys) {
            return '';
        } else {
            return ($uniqueKeys ? ' with' : ' without') . ' unique keys';
        }
    }


    public function walkJsonArrayAgg(nodes\json\JsonArrayAgg $expression): string
    {
        return 'json_arrayagg(' . $expression->value->dispatch($this)
            . (null === $expression->order ? '' : ' order by ' . \implode(', ', $expression->order->dispatch($this)))
            . $this->jsonOnNullClause($expression->absentOnNull)
            . $this->walkCommonJsonAggregateFields($expression);
    }

    public function walkJsonObjectAgg(nodes\json\JsonObjectAgg $expression): string
    {
        return 'json_objectagg(' . $expression->keyValue->dispatch($this)
            . $this->jsonOnNullClause($expression->absentOnNull)
            . $this->jsonUniqueKeysClause($expression->uniqueKeys)
            . $this->walkCommonJsonAggregateFields($expression);
    }

    public function walkJsonArrayValueList(nodes\json\JsonArrayValueList $expression): string
    {
        return 'json_array('
            . $this->implode(', ', $expression->arguments->dispatch($this))
            . $this->jsonOnNullClause($expression->absentOnNull)
            . (null === $expression->returning ? '' : ' ' . $expression->returning->dispatch($this))
            . ')';
    }

    public function walkJsonArraySubselect(nodes\json\JsonArraySubselect $expression): string
    {
        return 'json_array('
            . $expression->query->dispatch($this)
            . (null === $expression->format ? '' : ' ' . $expression->format->dispatch($this))
            . (null === $expression->returning ? '' : ' ' . $expression->returning->dispatch($this))
            . ')';
    }

    public function walkJsonObject(nodes\json\JsonObject $expression): string
    {
        return 'json_object(' . \implode(', ', $expression->arguments->dispatch($this))
            . $this->jsonOnNullClause($expression->absentOnNull)
            . $this->jsonUniqueKeysClause($expression->uniqueKeys)
            . (null === $expression->returning ? '' : ' ' . $expression->returning->dispatch($this))
            . ')';
    }

    public function walkJsonConstructor(nodes\json\JsonConstructor $expression): string
    {
        return 'json(' . $expression->expression->dispatch($this)
            . $this->jsonUniqueKeysClause($expression->uniqueKeys)
            . ')';
    }

    public function walkJsonScalar(nodes\json\JsonScalar $expression): string
    {
        return 'json_scalar(' . $expression->expression->dispatch($this) . ')';
    }

    public function walkJsonSerialize(nodes\json\JsonSerialize $expression): string
    {
        return 'json_serialize(' . $expression->expression->dispatch($this)
            . (null === $expression->returning ? '' : ' ' . $expression->returning->dispatch($this))
            . ')';
    }

    public function walkJsonArgument(nodes\json\JsonArgument $clause): string
    {
        return $clause->value->dispatch($this) . ' as ' . $clause->alias->dispatch($this);
    }

    protected function walkCommonJsonQueryFields(nodes\json\JsonQueryCommon $expression): string
    {
        return $expression->context->dispatch($this)
            . ', ' . $expression->path->dispatch($this)
            . (
                0 === \count($expression->passing)
                ? ''
                : ' passing ' . \implode(', ', $expression->passing->dispatch($this))
            );
    }

    protected function jsonQueryBehaviours(nodes\GenericNode $expression): string
    {
        $result = '';
        if (!empty($expression->onEmpty)) {
            if ($expression->onEmpty instanceof nodes\ScalarExpression) {
                $result .= ' default ' . $expression->onEmpty->dispatch($this) . ' on empty';
            } else {
                $result .= ' ' . $expression->onEmpty->value . ' on empty';
            }
        }
        if (!empty($expression->onError)) {
            if ($expression->onError instanceof nodes\ScalarExpression) {
                $result .= ' default ' . $expression->onError->dispatch($this) . ' on error';
            } else {
                $result .= ' ' . $expression->onError->value . ' on error';
            }
        }
        return $result;
    }

    public function walkJsonExists(nodes\json\JsonExists $expression): string
    {
        return 'json_exists(' . $this->walkCommonJsonQueryFields($expression)
            . $this->jsonQueryBehaviours($expression)
            . ')';
    }

    public function walkJsonValue(nodes\json\JsonValue $expression): string
    {
        return 'json_value(' . $this->walkCommonJsonQueryFields($expression)
            . (null === $expression->returning ? '' : ' ' . $expression->returning->dispatch($this))
            . $this->jsonQueryBehaviours($expression)
            . ')';
    }

    public function walkJsonQuery(nodes\json\JsonQuery $expression): string
    {
        return 'json_query(' . $this->walkCommonJsonQueryFields($expression)
            . (null === $expression->returning ? '' : ' ' . $expression->returning->dispatch($this))
            . (null === $expression->wrapper ? '' : ' ' . $expression->wrapper->value . ' wrapper')
            . (null === $expression->keepQuotes ? '' : ' ' . ($expression->keepQuotes ? 'keep' : 'omit') . ' quotes')
            . $this->jsonQueryBehaviours($expression)
            . ')';
    }

    public function walkJsonTable(nodes\range\JsonTable $rangeItem): string
    {
        $this->indentLevel++;
        $lines = [($rangeItem->lateral ? 'lateral ' : '') . 'json_table('];

        $lines[] = $this->getIndent() . $rangeItem->context->dispatch($this)
            . ', ' . $rangeItem->path->dispatch($this)
            . (null === $rangeItem->pathName ? '' : ' as ' . $rangeItem->pathName->dispatch($this));

        if (0 < \count($rangeItem->passing)) {
            $lines[] = $this->implode($this->getIndent() . 'passing ', $rangeItem->passing->dispatch($this), ',');
        }

        $lines[] = $this->getIndent() . 'columns (';
        $lines[] = $this->walkJsonColumnDefinitionList($rangeItem->columns);
        $lines[] = $this->getIndent() . ')';

        if (null !== $rangeItem->onError) {
            $lines[] = $this->getIndent() . $rangeItem->onError->value . ' on error';
        }

        $this->indentLevel--;

        $sql = \implode($this->options['linebreak'] ?: ' ', $lines)
            . $this->options['linebreak'] . $this->getIndent() . ')';
        if ($rangeItem->tableAlias || $rangeItem->columnAliases) {
            $sql .= $this->getFromItemAliases($rangeItem);
        }

        return $sql;
    }

    protected function walkJsonColumnDefinitionList(nodes\range\json\JsonColumnDefinitionList $columns): string
    {
        $this->indentLevel++;
        $glue = $this->options['linebreak']
            ? ',' . $this->options['linebreak'] . $this->getIndent()
            : ', ';
        $result = $this->getIndent() . \implode($glue, $this->walkGenericNodeList($columns));
        $this->indentLevel--;

        return $result;
    }

    public function walkJsonExistsColumnDefinition(nodes\range\json\JsonExistsColumnDefinition $column): string
    {
        return $column->name->dispatch($this) . ' ' . $column->type->dispatch($this)
            . ' exists' . (null === $column->path ? '' : ' path ' . $column->path->dispatch($this))
            .  $this->jsonQueryBehaviours($column);
    }

    public function walkJsonOrdinalityColumnDefinition(nodes\range\json\JsonOrdinalityColumnDefinition $column): string
    {
        return $column->name->dispatch($this) . ' for ordinality';
    }

    public function walkJsonRegularColumnDefinition(nodes\range\json\JsonRegularColumnDefinition $column): string
    {
        return $column->name->dispatch($this) . ' ' . $column->type->dispatch($this)
            . (null === $column->format ? '' : ' ' . $column->format->dispatch($this))
            . (null === $column->path ? '' : ' path ' . $column->path->dispatch($this))
            . (null === $column->wrapper ? '' : ' ' . $column->wrapper->value . ' wrapper')
            . (null === $column->keepQuotes ? '' : ' ' . ($column->keepQuotes ? 'keep' : 'omit') . ' quotes')
            . $this->jsonQueryBehaviours($column);
    }

    public function walkJsonNestedColumns(nodes\range\json\JsonNestedColumns $column): string
    {
        $lines = [
            'nested path ' . $column->path->dispatch($this)
            . (null === $column->pathName ? '' : ' as ' . $column->pathName->dispatch($this))
            . ' columns ('
        ];
        $lines[] = $this->walkJsonColumnDefinitionList($column->columns);
        $lines[] = $this->getIndent() . ')';

        return \implode($this->options['linebreak'] ?: ' ', $lines);
    }

    /**
     * Returns an array of code points corresponding to characters in UTF-8 string
     *
     * @param string $string
     * @return int[]
     */
    protected static function utf8ToCodePoints(string $string): array
    {
        $codePoints = [];

        for ($i = 0, $length = \strlen($string); $i < $length; $i++) {
            $code = \ord($string[$i]);
            if ($code < 0x80) {
                $codePoint = $code;

            } elseif (0xC0 === ($code & 0xE0)) {
                if (
                    $i >= $length - 1
                    || 0x80 !== (\ord($string[$i + 1]) & 0xC0)
                ) {
                    throw new exceptions\InvalidArgumentException('Invalid UTF-8: incomplete multibyte character');
                }

                $codePoint = (($code & 0x1F) << 6) + (\ord($string[++$i]) & 0x3F);

                if ($codePoint < 0x80) {
                    throw new exceptions\InvalidArgumentException('Invalid UTF-8: overlong encoding');
                }

            } elseif (0xE0 === ($code & 0xF0)) {
                if (
                    $i >= $length - 2
                    || 0x80 !== (\ord($string[$i + 1]) & 0xC0)
                    || 0x80 !== (\ord($string[$i + 2]) & 0xC0)
                ) {
                    throw new exceptions\InvalidArgumentException('Invalid UTF-8: incomplete multibyte character');
                }

                $codePoint = (($code & 0xF) << 12) + ((\ord($string[++$i]) & 0x3F) << 6) + (\ord($string[++$i]) & 0x3F);

                if ($codePoint < 0x800) {
                    throw new exceptions\InvalidArgumentException('Invalid UTF-8: overlong encoding');
                } elseif ($codePoint >= 0xD800 && $codePoint <= 0xDFFF) {
                    throw new exceptions\InvalidArgumentException('Invalid code point encoded in UTF-8');
                }

            } elseif (0xF0 === ($code & 0xF8)) {
                if (
                    $i >= $length - 3
                    || 0x80 !== (\ord($string[$i + 1]) & 0xC0)
                    || 0x80 !== (\ord($string[$i + 2]) & 0xC0)
                    || 0x80 !== (\ord($string[$i + 3]) & 0xC0)
                ) {
                    throw new exceptions\InvalidArgumentException('Invalid UTF-8: incomplete multibyte character');
                }

                $codePoint = (($code & 0x7) << 18) + ((\ord($string[++$i]) & 0x3F) << 12)
                             + ((\ord($string[++$i]) & 0x3F) << 6) + (\ord($string[++$i]) & 0x3F);

                if ($codePoint < 0x10000) {
                    throw new exceptions\InvalidArgumentException('Invalid UTF-8: overlong encoding');
                } elseif ($codePoint > 0x10FFFF) {
                    throw new exceptions\InvalidArgumentException('Invalid code point encoded in UTF-8');
                }

            } else {
                throw new exceptions\InvalidArgumentException('Invalid byte in UTF-8 string');
            }
            $codePoints[] = $codePoint;
        }

        return $codePoints;
    }
}
