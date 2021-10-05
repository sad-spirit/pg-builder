<?php

/**
 * Query builder for Postgres backed by SQL parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

// phpcs:disable PSR1.Methods.CamelCapsMethodName
/**
 * Recursive descent parser for PostgreSQL's dialect of SQL
 *
 * Parses an SQL query converted to TokenStream by Lexer, returns the syntax tree for it.
 *
 * The parsing methods are named parse*() and dispatched through __call() due to
 * amount of boilerplate code needed. The main method is parseStatement(), several
 * more are available to facilitate parsing of expression parts rather than complete
 * SQL statements.
 *
 * @method Statement                        parseStatement($input)
 * @method SelectCommon                     parseSelectStatement($input)
 * @method nodes\lists\ExpressionList       parseExpressionList($input)
 * @method nodes\ScalarExpression           parseExpression($input)
 * @method nodes\expressions\RowExpression  parseRowConstructor($input)
 * @method nodes\expressions\RowExpression  parseRowConstructorNoKeyword($input)
 * @method nodes\lists\RowList              parseRowList($input)
 * @method nodes\lists\TargetList           parseTargetList($input)
 * @method nodes\TargetElement              parseTargetElement($input)
 * @method nodes\lists\FromList             parseFromList($input)
 * @method nodes\range\FromElement          parseFromElement($input)
 * @method nodes\lists\OrderByList          parseOrderByList($input)
 * @method nodes\OrderByElement             parseOrderByElement($input)
 * @method nodes\lists\WindowList           parseWindowList($input)
 * @method nodes\WindowDefinition           parseWindowDefinition($input)
 * @method nodes\LockingElement             parseLockingElement($input)
 * @method nodes\lists\LockList             parseLockingList($input)
 * @method nodes\range\UpdateOrDeleteTarget parseRelationExpressionOptAlias($input)
 * @method nodes\range\InsertTarget         parseInsertTarget($input)
 * @method nodes\QualifiedName              parseQualifiedName($input)
 * @method nodes\lists\SetClauseList        parseSetClauseList($input)
 * @method nodes\SingleSetClause|nodes\MultipleSetClause parseSetClause($input)
 * @method nodes\lists\SetTargetList        parseInsertTargetList($input)
 * @method nodes\SetTargetElement           parseSetTargetElement($input)
 * @method nodes\ScalarExpression           parseExpressionWithDefault($input)
 * @method nodes\WithClause                 parseWithClause($input)
 * @method nodes\CommonTableExpression      parseCommonTableExpression($input)
 * @method nodes\lists\IdentifierList       parseColIdList($input)
 * @method nodes\OnConflictClause           parseOnConflict($input)
 * @method nodes\IndexParameters            parseIndexParameters($input)
 * @method nodes\IndexElement               parseIndexElement($input)
 * @method nodes\group\GroupByClause        parseGroupByClause($input)
 * @method nodes\ScalarExpression|nodes\group\GroupByElement parseGroupByElement($input)
 * @method nodes\xml\XmlNamespaceList       parseXmlNamespaceList($input)
 * @method nodes\xml\XmlNamespace           parseXmlNamespace($input)
 * @method nodes\xml\XmlColumnList          parseXmlColumnList($input)
 * @method nodes\xml\XmlColumnDefinition    parseXmlColumnDefinition($input)
 * @method nodes\TypeName                   parseTypeName($input)
 */
class Parser
{
    /**
     * Mathematical operators
     *
     * mathOp production from gram.y
     */
    private const MATH_OPERATORS = ['+', '-', '*', '/', '%', '^', '<', '>', '=', '<=', '>=', '!=', '<>'];

    /**
     * Subquery expressions that can appear at right side of most scalar operators
     *
     * sub_type production from gram.y
     */
    private const SUBQUERY_EXPRESSIONS = ['any', 'all', 'some'];

    /**
     * Known system functions that must appear with parentheses
     *
     * From func_expr_common_subexpr production in gram.y
     */
    private const SYSTEM_FUNCTIONS = [
        'cast', 'extract', 'overlay', 'position', 'substring', 'treat', 'trim',
        'nullif', 'coalesce', 'greatest', 'least', 'xmlconcat', 'xmlelement',
        'xmlexists', 'xmlforest', 'xmlparse', 'xmlpi', 'xmlroot', 'xmlserialize',
        'normalize'
    ];

    /**
     * Returned by {@link checkContentsOfParentheses()} if subquery is found
     */
    private const PARENTHESES_SELECT     = 'select';

    /**
     * Returned by {@link checkContentsOfParentheses()} if row constructor is found
     */
    private const PARENTHESES_ROW        = 'row';

    /**
     * Returned by {@link checkContentsOfParentheses()} if parentheses contain a scalar expression
     */
    private const PARENTHESES_EXPRESSION = 'expression';

    /**
     * Passed to {@link UpdateOrDeleteTarget()} to set expected format, allows only relation alias
     */
    private const RELATION_FORMAT_UPDATE = 'update';

    /**
     * Passed to {@link UpdateOrDeleteTarget()} to set expected format, allows only relation alias (which can be SET)
     */
    private const RELATION_FORMAT_DELETE = 'delete';

    /**
     * Checks for SQL standard date and time type names
     */
    private const STANDARD_TYPES_DATETIME  = ['time', 'timestamp'];

    /**
     * Checks for SQL standard character type names
     */
    private const STANDARD_TYPES_CHARACTER = ['character', 'char', 'varchar', 'nchar', 'national'];

    /**
     * Checks for SQL standard character type name(s)
     */
    private const STANDARD_TYPES_BIT       = 'bit';

    /**
     * Checks for SQL standard numeric type name(s)
     */
    private const STANDARD_TYPES_NUMERIC   = [
        'int', 'integer', 'smallint', 'bigint', 'real', 'float', 'decimal', 'dec', 'numeric', 'boolean', 'double'
    ];

    /**
     * Two-word names for SQL standard types
     */
    private const STANDARD_DOUBLE_WORD_TYPES = [
        'double'   => 'precision',
        'national' => ['character', 'char']
    ];

    /**
     * SQL standard type names that can be followed by VARYING keyword
     */
    private const STANDARD_TYPES_OPT_VARYING = [
        'bit'       => true,
        'character' => true,
        'char'      => true,
        'nchar'     => true,
        'national'  => true
    ];

    /**
     * SQL standard type names that cannot have modifiers
     */
    private const STANDARD_TYPES_NO_MODIFIERS = [
        'int'       => true,
        'integer'   => true,
        'smallint'  => true,
        'bigint'    => true,
        'real'      => true,
        'boolean'   => true,
        'double'    => true
    ];

    /**
     * Mapping from SQL standard types to their equivalents in pg_catalog.pg_type
     */
    private const STANDARD_TYPES_MAPPING = [
        'int'              => 'int4',
        'integer'          => 'int4',
        'smallint'         => 'int2',
        'bigint'           => 'int8',
        'real'             => 'float4',
        'decimal'          => 'numeric',
        'dec'              => 'numeric',
        'numeric'          => 'numeric',
        'boolean'          => 'bool',
        'double precision' => 'float8'
    ];

    /**
     * Keyword sequence checks for {@link WindowFrameBound()} method
     */
    private const CHECKS_FRAME_BOUND = [
        ['unbounded', 'preceding'],
        ['unbounded', 'following'],
        ['current', 'row']
    ];

    /**
     * Keyword sequence checks for {@link PatternMatchingExpression()} method
     */
    private const CHECKS_PATTERN_MATCHING = [
        ['like'],
        ['not', 'like'],
        ['ilike'],
        ['not', 'ilike'],
        // the following cannot be applied to subquery operators
        ['similar', 'to'],
        ['not', 'similar', 'to']
    ];

    /**
     * Keyword sequence checks for {@link IsWhateverExpression()} method
     */
    private const CHECKS_IS_WHATEVER = [
        ['null'],
        ['true'],
        ['false'],
        ['unknown'],
        ['normalized'],
        [['nfc', 'nfd', 'nfkc', 'nfkd'], 'normalized']
    ];

    /**
     * Keywords that can appear in {@link ExpressionAtom()} on their own right
     */
    private const ATOM_KEYWORDS = [
        'row', 'array', 'exists', 'case', 'grouping', 'true', 'false', 'null'
    ];

    /**
     * A bit mask of Token types that are checked first in {@link ExpressionAtom()}
     */
    private const ATOM_SPECIAL_TYPES = Token::TYPE_SPECIAL | Token::TYPE_PARAMETER | Token::TYPE_LITERAL;

    /**
     * Token types that can appear as the first part of an Identifier in {@link NamedExpressionAtom()}
     */
    private const ATOM_IDENTIFIER_TYPES = [
        Token::TYPE_TYPE_FUNC_NAME_KEYWORD => true,
        Token::TYPE_COL_NAME_KEYWORD       => true,
        Token::TYPE_UNRESERVED_KEYWORD     => true,
        Token::TYPE_IDENTIFIER             => true
    ];

    /**
     * Methods that are exposed through __call()
     * @var array
     */
    private const CALLABLE = [
        'statement'                  => true,
        'selectstatement'            => true,
        'expression'                 => true,
        'expressionlist'             => true,
        'rowconstructor'             => true,
        'rowconstructornokeyword'    => true,
        'rowlist'                    => true,
        'targetlist'                 => true,
        'targetelement'              => true,
        'fromlist'                   => true,
        'fromelement'                => true,
        'windowlist'                 => true,
        'windowdefinition'           => true, // element of WindowList
        'orderbylist'                => true,
        'orderbyelement'             => true,
        'lockinglist'                => true,
        'lockingelement'             => true,
        'relationexpressionoptalias' => true,
        'inserttarget'               => true,
        'qualifiedname'              => true,
        'setclause'                  => true, // for UPDATE
        'setclauselist'              => true, // for UPDATE
        'settargetelement'           => true, // for INSERT
        'inserttargetlist'           => true, // for INSERT
        'expressionwithdefault'      => true,
        'withclause'                 => true,
        'commontableexpression'      => true,
        'colidlist'                  => true,
        'indexparameters'            => true,
        'indexelement'               => true,
        'onconflict'                 => true,
        'groupbyclause'              => true,
        'groupbyelement'             => true,
        'xmlnamespacelist'           => true,
        'xmlnamespace'               => true,
        'xmlcolumnlist'              => true,
        'xmlcolumndefinition'        => true,
        'typename'                   => true
    ];

    /**
     * @var Lexer
     */
    private $lexer;

    /**
     * @var CacheItemPoolInterface|null
     */
    private $cache;

    /**
     * @var TokenStream
     */
    private $stream;

    /**
     * Guesses the type of parenthesised expression
     *
     * Parentheses may contain
     *  * expressions: (foo + bar)
     *  * row constructors: (foo, bar)
     *  * subselects (select foo, bar)
     *
     * @return null|string Either of 'select', 'row' or 'expression'. Null if stream was not on opening parenthesis
     * @throws exceptions\SyntaxException in case of unclosed parenthesis
     */
    private function checkContentsOfParentheses(): ?string
    {
        $openParens = [];
        $lookIdx    = 0;
        while ($this->stream->look($lookIdx)->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
            $openParens[] = $lookIdx++;
        }
        if (0 === $lookIdx) {
            return null;
        }

        if (!$this->stream->look($lookIdx)->matches(Token::TYPE_KEYWORD, ['values', 'select', 'with'])) {
            $selectLevel = false;
        } elseif (1 === ($selectLevel = count($openParens))) {
            return self::PARENTHESES_SELECT;
        }

        do {
            $token = $this->stream->look(++$lookIdx);
            if (!$token->matches(Token::TYPE_SPECIAL_CHAR)) {
                continue;
            }
            switch ($token->getValue()) {
                case '[':
                    $lookIdx = $this->skipParentheses($lookIdx, true) - 1;
                    break;

                case '(':
                    $openParens[] = $lookIdx;
                    break;

                case ',':
                    if (1 === count($openParens) && !$selectLevel) {
                        return self::PARENTHESES_ROW;
                    }
                    break;

                case ')':
                    if (1 < count($openParens) && $selectLevel === count($openParens)) {
                        if (
                            $this->stream->look($lookIdx + 1)->matches(
                                Token::TYPE_KEYWORD,
                                ['union', 'intersect', 'except', 'order', 'limit', 'offset',
                                    'for' /* ...update */, 'fetch' /* SQL:2008 limit */]
                            )
                            || $this->stream->look($lookIdx + 1)->matches(Token::TYPE_SPECIAL_CHAR, ')')
                        ) {
                            // this addresses stuff like ((select 1) order by 1)
                            $selectLevel--;
                        } else {
                            $selectLevel = false;
                        }
                    }
                    array_pop($openParens);
            }
        } while (!empty($openParens) && !$token->matches(Token::TYPE_EOF));

        if (!empty($openParens)) {
            $token = $this->stream->look(array_shift($openParens));
            throw exceptions\SyntaxException::atPosition(
                "Unbalanced '('",
                $this->stream->getSource(),
                $token->getPosition()
            );
        }

        return $selectLevel ? self::PARENTHESES_SELECT : self::PARENTHESES_EXPRESSION;
    }

    /**
     * Skips the expression enclosed in parentheses
     *
     * @param int  $start  Starting lookahed position in token stream
     * @param bool $square Whether we are skipping square brackets [] rather than ()
     * @return int Position after the closing ']' or ')'
     * @throws exceptions\SyntaxException in case of unclosed parentheses
     */
    private function skipParentheses(int $start, bool $square = false): int
    {
        $lookIdx    = $start;
        $openParens = 1;

        do {
            $token = $this->stream->look(++$lookIdx);
            switch ($token->getType()) {
                case Token::TYPE_SPECIAL_CHAR:
                    if ($token->getValue() === ($square ? '[' : '(')) {
                        $openParens++;
                    } elseif ($token->getValue() === ($square ? ']' : ')')) {
                        $openParens--;
                    }
                    break;
                case Token::TYPE_EOF:
                    break 2;
            }
        } while ($openParens > 0);

        if (0 !== $openParens) {
            $token = $this->stream->look($start);
            throw exceptions\SyntaxException::atPosition(
                "Unbalanced '" . ($square ? '[' : '(') . "'",
                $this->stream->getSource(),
                $token->getPosition()
            );
        }

        return $lookIdx + 1;
    }


    /**
     * Tests whether current position of stream matches a (possibly schema-qualified) operator
     *
     * @return bool
     */
    private function matchesOperator(): bool
    {
        return $this->stream->matches(Token::TYPE_OPERATOR)
               || $this->stream->matchesKeyword('operator')
                  && $this->stream->look(1)->matches(Token::TYPE_SPECIAL_CHAR, '(');
    }


    /**
     * Tests whether current position of stream matches 'func_name' production from PostgreSQL's grammar
     *
     * Actually func_name allows indirection via array subscripts and appearance of '*' in
     * name, these are only disallowed later in processing, we disallow these here.
     *
     * @return int|false position after func_name if matches, false if not
     */
    private function matchesFuncName()
    {
        $firstType = $this->stream->getCurrent()->getType();
        if (!isset(self::ATOM_IDENTIFIER_TYPES[$firstType])) {
            return false;
        }
        $idx = 1;
        while (
            $this->stream->look($idx)->matches(Token::TYPE_SPECIAL_CHAR, '.')
            && ($this->stream->look($idx + 1)->matches(Token::TYPE_IDENTIFIER)
                || $this->stream->look($idx + 1)->matches(Token::TYPE_KEYWORD))
        ) {
            $idx += 2;
        }
        if (
            Token::TYPE_TYPE_FUNC_NAME_KEYWORD === $firstType && 1 < $idx
            || Token::TYPE_COL_NAME_KEYWORD === $firstType && 1 === $idx
        ) {
            // does not match func_name production
            return false;
        } else {
            return $idx;
        }
    }

    /**
     * Tests whether current position of stream matches a system function call
     *
     * @return bool
     */
    private function matchesSpecialFunctionCall(): bool
    {
        static $dontCheckParens = null;

        if (null === $dontCheckParens) {
            $dontCheckParens = array_merge(
                nodes\expressions\SQLValueFunction::NO_MODIFIERS,
                nodes\expressions\SQLValueFunction::OPTIONAL_MODIFIERS
            );
        }
        return $this->stream->matchesKeyword($dontCheckParens) // function-like stuff that doesn't need parentheses
               // known system functions that require parentheses
               || ($this->stream->matchesKeyword(self::SYSTEM_FUNCTIONS)
                   && $this->stream->look(1)->matches(Token::TYPE_SPECIAL_CHAR, '('))
               || ($this->stream->matchesKeywordSequence('collation', 'for') // COLLATION FOR (...)
                   && $this->stream->look(2)->matches(Token::TYPE_SPECIAL_CHAR, '('));
    }

    /**
     * Tests whether current position of stream matches a function call
     *
     * @return bool
     */
    private function matchesFunctionCall(): bool
    {
        return $this->matchesSpecialFunctionCall()
               || false !== ($idx = $this->matchesFuncName())
                  && $this->stream->look($idx)->matches(Token::TYPE_SPECIAL_CHAR, '(');
    }

    /**
     * Tests whether current position of stream looks like a type cast with standard type name
     *
     * i.e. "typename 'string constant'" where typename is SQL standard one: "integer" but not "int4"
     *
     * @return bool
     */
    private function matchesConstTypecast(): bool
    {
        static $constNames       = null;
        static $trailingTimezone = null;

        if (null === $constNames) {
            $constNames = array_merge(
                self::STANDARD_TYPES_CHARACTER,
                self::STANDARD_TYPES_NUMERIC,
                self::STANDARD_TYPES_DATETIME,
                [self::STANDARD_TYPES_BIT, 'interval']
            );
            $trailingTimezone = array_flip(self::STANDARD_TYPES_DATETIME);
        }

        if (!$this->stream->matchesKeyword($constNames)) {
            return false;
        }
        $base = $this->stream->getCurrent()->getValue();
        $idx  = 1;

        if (
            isset(self::STANDARD_DOUBLE_WORD_TYPES[$base])
            && !$this->stream->look($idx++)->matches(Token::TYPE_KEYWORD, self::STANDARD_DOUBLE_WORD_TYPES[$base])
        ) {
            return false;
        }

        if (
            isset(self::STANDARD_TYPES_OPT_VARYING[$base])
            && $this->stream->look($idx)->matches(Token::TYPE_KEYWORD, 'varying')
        ) {
            $idx++;
        }

        if (
            !isset(self::STANDARD_TYPES_NO_MODIFIERS[$base])
            && $this->stream->look($idx)->matches(Token::TYPE_SPECIAL_CHAR, '(')
        ) {
            $idx = $this->skipParentheses($idx);
        }

        if (
            isset($trailingTimezone[$base])
            && $this->stream->look($idx)->matches(Token::TYPE_KEYWORD, ['with', 'without'])
        ) {
            $idx += 3;
        }

        return $this->stream->look($idx)->matches(Token::TYPE_STRING);
    }

    /**
     * Constructor, sets Lexer and Cache implementations to use
     *
     * It is recommended to always use cache in production: loading AST from cache is generally 3-4 times faster
     * than parsing.
     *
     * @param Lexer                       $lexer
     * @param CacheItemPoolInterface|null $cache
     */
    public function __construct(Lexer $lexer, CacheItemPoolInterface $cache = null)
    {
        $this->lexer = $lexer;
        $this->cache = $cache;
    }

    /**
     * Sets the cache object used for storing of SQL parse results
     *
     * @param CacheItemPoolInterface $cache
     */
    public function setCache(CacheItemPoolInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * Magic method for function overloading
     *
     * The method allows calling parseWhatever() methods that map to protected Whatever() methods
     * listed in $callable static property.
     *
     * @param string $name
     * @param array  $arguments
     * @return mixed
     * @throws exceptions\BadMethodCallException
     * @throws exceptions\SyntaxException
     */
    public function __call(string $name, array $arguments)
    {
        if (
            !preg_match('/^parse([a-zA-Z]+)$/', $name, $matches)
            || !isset(self::CALLABLE[strtolower($matches[1])])
        ) {
            throw new exceptions\BadMethodCallException("The method '{$name}' is not available");
        }

        if (null !== $this->cache) {
            $source = $arguments[0] instanceof TokenStream ? $arguments[0]->getSource() : (string)$arguments[0];
            try {
                $cacheItem = $this->cache->getItem('parsetree-' . md5('{' . $name . '}' . $source));
                if ($cacheItem->isHit()) {
                    return clone $cacheItem->get();
                }
            } catch (InvalidArgumentException $e) {
            }
        }

        if ($arguments[0] instanceof TokenStream) {
            $this->stream = $arguments[0];
            $this->stream->reset();
        } else {
            $this->stream = $this->lexer->tokenize($arguments[0]);
        }

        $parsed = $this->{$matches[1]}();

        if (!$this->stream->isEOF()) {
            throw exceptions\SyntaxException::expectationFailed(
                Token::TYPE_EOF,
                null,
                $this->stream->getCurrent(),
                $this->stream->getSource()
            );
        }

        if (null !== $this->cache && isset($cacheItem)) {
            $this->cache->save($cacheItem->set(clone $parsed));
        }

        return $parsed;
    }

    protected function Statement(): Statement
    {
        if ($this->stream->matchesKeyword('with')) {
            $withClause = $this->WithClause();
        }

        if (
            $this->stream->matchesKeyword(['select', 'values'])
            || $this->stream->matchesSpecialChar('(')
        ) {
            $stmt = $this->SelectStatement();
            if (!empty($withClause)) {
                if (0 < count($stmt->with)) {
                    throw new exceptions\SyntaxException('Multiple WITH clauses are not allowed');
                }
                $stmt->with = $withClause;
            }
            return $stmt;

        } elseif ($this->stream->matchesKeyword('insert')) {
            $stmt = $this->InsertStatement();

        } elseif ($this->stream->matchesKeyword('update')) {
            $stmt = $this->UpdateStatement();

        } elseif ($this->stream->matchesKeyword('delete')) {
            $stmt = $this->DeleteStatement();

        } else {
            throw new exceptions\SyntaxException(
                'Unexpected ' . $this->stream->getCurrent()
                . ', expecting SELECT / INSERT / UPDATE / DELETE statement'
            );
        }

        if (!empty($withClause)) {
            $stmt->with = $withClause;
        }
        return $stmt;
    }

    protected function SelectStatement(): SelectCommon
    {
        if ($this->stream->matchesKeyword('with')) {
            $withClause = $this->WithClause();
        }

        $stmt = $this->SelectIntersect();

        while ($this->stream->matchesKeyword(['union', 'except'])) {
            $setOp = $this->stream->next()->getValue();
            if ($this->stream->matchesKeyword(['all', 'distinct'])) {
                $setOp .= ('all' === $this->stream->next()->getValue() ? ' all' : '');
            }
            $stmt = new SetOpSelect($stmt, $this->SelectIntersect(), $setOp);
        }

        if (!empty($withClause)) {
            if (0 < count($stmt->with)) {
                throw new exceptions\SyntaxException(
                    'Multiple WITH clauses are not allowed'
                );
            }
            $stmt->with = $withClause;
        }

        // Per SQL spec ORDER BY and later clauses apply to a result of set operation,
        // not to a single participating SELECT
        if ($this->stream->matchesKeywordSequence('order', 'by')) {
            if (count($stmt->order) > 0) {
                throw exceptions\SyntaxException::atPosition(
                    'Multiple ORDER BY clauses are not allowed',
                    $this->stream->getSource(),
                    $this->stream->getCurrent()->getPosition()
                );
            }
            $this->stream->skip(2);
            $stmt->order->replace($this->OrderByList());
        }

        // LIMIT / OFFSET clause and FOR [UPDATE] clause may come in any order
        if ($this->stream->matchesKeyword(['for', 'limit', 'offset', 'fetch'])) {
            if ('for' === $this->stream->getCurrent()->getValue()) {
                // locking clause first
                $this->ForLockingClause($stmt);
                if ($this->stream->matchesKeyword(['limit', 'offset', 'fetch'])) {
                    $this->LimitOffsetClause($stmt);
                }

            } else {
                // limit clause first
                $this->LimitOffsetClause($stmt);
                if ($this->stream->matchesKeyword('for')) {
                    $this->ForLockingClause($stmt);
                }
            }
        }

        return $stmt;
    }

    protected function InsertStatement(): Insert
    {
        if ($this->stream->matchesKeyword('with')) {
            $withClause = $this->WithClause();
        }
        $this->stream->expect(Token::TYPE_KEYWORD, 'insert');
        $this->stream->expect(Token::TYPE_KEYWORD, 'into');

        $stmt = new Insert($this->InsertTarget());
        if (!empty($withClause)) {
            $stmt->with = $withClause;
        }

        if ($this->stream->matchesKeywordSequence('default', 'values')) {
            $this->stream->skip(2);
        } else {
            if (
                $this->stream->matchesSpecialChar('(')
                && self::PARENTHESES_SELECT !== $this->checkContentsOfParentheses()
            ) {
                $this->stream->next();
                $stmt->cols->replace($this->InsertTargetList());
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }
            if ($this->stream->matchesKeyword('overriding')) {
                $this->stream->next();
                $stmt->setOverriding(
                    $this->stream->expect(Token::TYPE_KEYWORD, ['user', 'system'])->getValue()
                );
                $this->stream->expect(Token::TYPE_KEYWORD, 'value');
            }
            $stmt->values = $this->SelectStatement();
        }

        if ($this->stream->matchesKeywordSequence('on', 'conflict')) {
            $this->stream->skip(2);
            $stmt->onConflict = $this->OnConflict();
        }

        if ($this->stream->matchesKeyword('returning')) {
            $this->stream->next();
            $stmt->returning->replace($this->TargetList());
        }

        return $stmt;
    }

    protected function UpdateStatement(): Update
    {
        if ($this->stream->matchesKeyword('with')) {
            $withClause = $this->WithClause();
        }

        $this->stream->expect(Token::TYPE_KEYWORD, 'update');
        $relation = $this->UpdateOrDeleteTarget();
        $this->stream->expect(Token::TYPE_KEYWORD, 'set');

        $stmt = new Update($relation, $this->SetClauseList());

        if (!empty($withClause)) {
            $stmt->with = $withClause;
        }

        if ($this->stream->matchesKeyword('from')) {
            $this->stream->next();
            $stmt->from->replace($this->FromList());
        }
        if ($this->stream->matchesKeyword('where')) {
            $this->stream->next();
            if ($this->stream->matchesKeywordSequence('current', 'of')) {
                throw new exceptions\NotImplementedException('WHERE CURRENT OF clause is not supported');
            }
            $stmt->where->condition = $this->Expression();
        }
        if ($this->stream->matchesKeyword('returning')) {
            $this->stream->next();
            $stmt->returning->replace($this->TargetList());
        }

        return $stmt;
    }

    protected function DeleteStatement(): Delete
    {
        if ($this->stream->matchesKeyword('with')) {
            $withClause = $this->WithClause();
        }
        $this->stream->expect(Token::TYPE_KEYWORD, 'delete');
        $this->stream->expect(Token::TYPE_KEYWORD, 'from');

        $stmt = new Delete($this->UpdateOrDeleteTarget(self::RELATION_FORMAT_DELETE));

        if (!empty($withClause)) {
            $stmt->with = $withClause;
        }

        if ($this->stream->matchesKeyword('using')) {
            $this->stream->next();
            $stmt->using->replace($this->FromList());
        }
        if ($this->stream->matchesKeyword('where')) {
            $this->stream->next();
            if ($this->stream->matchesKeywordSequence('current', 'of')) {
                throw new exceptions\NotImplementedException('WHERE CURRENT OF clause is not supported');
            }
            $stmt->where->condition = $this->Expression();
        }
        if ($this->stream->matchesKeyword('returning')) {
            $this->stream->next();
            $stmt->returning->replace($this->TargetList());
        }

        return $stmt;
    }

    protected function WithClause(): nodes\WithClause
    {
        $this->stream->expect(Token::TYPE_KEYWORD, 'with');
        if ($recursive = $this->stream->matchesKeyword('recursive')) {
            $this->stream->next();
        }

        $commonTableExpressions = [$this->CommonTableExpression()];
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $commonTableExpressions[] = $this->CommonTableExpression();
        }

        return new nodes\WithClause($commonTableExpressions, $recursive);
    }

    protected function CommonTableExpression(): nodes\CommonTableExpression
    {
        $alias         = $this->ColId();
        $columnAliases = new nodes\lists\IdentifierList();
        $materialized  = null;
        $search        = null;
        $cycle         = null;
        if ($this->stream->matchesSpecialChar('(')) {
            do {
                $this->stream->next();
                $columnAliases[] = $this->ColId();
            } while ($this->stream->matchesSpecialChar(','));
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        }
        $this->stream->expect(Token::TYPE_KEYWORD, 'as');

        if ($this->stream->matchesKeyword('materialized')) {
            $materialized = true;
            $this->stream->next();
        } elseif ($this->stream->matchesKeyword('not')) {
            $materialized = false;
            $this->stream->next();
            $this->stream->expect(Token::TYPE_KEYWORD, 'materialized');
        }

        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        $statement = $this->Statement();
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        if ($this->stream->matchesKeyword('search')) {
            $search = $this->SearchClause();
        }
        if ($this->stream->matchesKeyword('cycle')) {
            $cycle = $this->CycleClause();
        }

        return new nodes\CommonTableExpression($statement, $alias, $columnAliases, $materialized, $search, $cycle);
    }

    public function SearchClause(): nodes\cte\SearchClause
    {
        $this->stream->expect(Token::TYPE_KEYWORD, 'search');
        $first = $this->stream->expect(Token::TYPE_KEYWORD, ['breadth', 'depth']);
        $this->stream->expect(Token::TYPE_KEYWORD, 'first');

        $this->stream->expect(Token::TYPE_KEYWORD, 'by');
        $trackColumns = $this->ColIdList();

        $this->stream->expect(Token::TYPE_KEYWORD, 'set');
        $sequenceColumn = $this->ColId();

        return new nodes\cte\SearchClause('breadth' === $first->getValue(), $trackColumns, $sequenceColumn);
    }

    public function CycleClause(): nodes\cte\CycleClause
    {
        $this->stream->expect(Token::TYPE_KEYWORD, 'cycle');
        $trackColumns = $this->ColIdList();

        $this->stream->expect(Token::TYPE_KEYWORD, 'set');
        $markColumn = $this->ColId();

        $markValue   = null;
        $markDefault = null;
        if ($this->stream->matchesKeyword('to')) {
            $this->stream->next();
            $markValue = $this->ConstantExpression();
            $this->stream->expect(Token::TYPE_KEYWORD, 'default');
            $markDefault = $this->ConstantExpression();
        }

        $this->stream->expect(Token::TYPE_KEYWORD, 'using');
        $pathColumn = $this->ColId();

        return new nodes\cte\CycleClause($trackColumns, $markColumn, $pathColumn, $markValue, $markDefault);
    }

    protected function ForLockingClause(SelectCommon $stmt): void
    {
        if ($this->stream->matchesKeywordSequence('for', 'read', 'only')) {
            // this isn't quite documented but means "no locking" judging by the grammar
            $this->stream->skip(3);
            return;
        }

        if ($stmt instanceof Values) {
            throw exceptions\SyntaxException::atPosition(
                'SELECT FOR UPDATE/SHARE cannot be applied to VALUES',
                $this->stream->getSource(),
                $this->stream->getCurrent()->getPosition()
            );
        }

        // multiple locking clauses are allowed, so just append
        $stmt->locking->merge($this->LockingList());
    }

    protected function LockingList(): nodes\lists\LockList
    {
        $list = new nodes\lists\LockList();

        do {
            $list[] = $this->LockingElement();
        } while ($this->stream->matchesKeyword('for'));

        return $list;
    }

    protected function LockingElement(): nodes\LockingElement
    {
        $this->stream->expect(Token::TYPE_KEYWORD, 'for');
        switch (
            $strength = $this->stream->expect(Token::TYPE_KEYWORD, ['update', 'no', 'share', 'key'])
                    ->getValue()
        ) {
            case 'no':
                $strength .= ' ' . $this->stream->expect(Token::TYPE_KEYWORD, 'key')->getValue()
                         . ' ' . $this->stream->expect(Token::TYPE_KEYWORD, 'update')->getValue();
                break;
            case 'key':
                $strength .= ' ' . $this->stream->expect(Token::TYPE_KEYWORD, 'share')->getValue();
        }

        $relations  = [];
        $noWait     = false;
        $skipLocked = false;

        if ($this->stream->matchesKeyword('of')) {
            do {
                $this->stream->next();
                $relations[] = $this->QualifiedName();
            } while ($this->stream->matchesSpecialChar(','));
        }

        if ($this->stream->matchesKeyword('nowait')) {
            $this->stream->next();
            $noWait = true;

        } elseif ($this->stream->matchesKeywordSequence('skip', 'locked')) {
            $this->stream->skip(2);
            $skipLocked = true;
        }

        return new nodes\LockingElement($strength, $relations, $noWait, $skipLocked);
    }

    protected function LimitOffsetClause(SelectCommon $stmt): void
    {
        // LIMIT and OFFSET clauses may come in any order
        if ($this->stream->matchesKeyword('offset')) {
            $this->OffsetClause($stmt);
            if ($this->stream->matchesKeyword(['limit', 'fetch'])) {
                $this->LimitClause($stmt);
            }
        } else {
            $this->LimitClause($stmt);
            if ($this->stream->matchesKeyword('offset')) {
                $this->OffsetClause($stmt);
            }
        }
    }

    protected function LimitClause(SelectCommon $stmt): void
    {
        if (null !== $stmt->limit) {
            throw exceptions\SyntaxException::atPosition(
                'Multiple LIMIT clauses are not allowed',
                $this->stream->getSource(),
                $this->stream->getCurrent()->getPosition()
            );
        }
        if ($this->stream->matchesKeyword('limit')) {
            // Traditional Postgres LIMIT clause
            $this->stream->next();
            if ($this->stream->matchesKeyword('all')) {
                $this->stream->next();
                $stmt->limit = new nodes\expressions\KeywordConstant(nodes\expressions\KeywordConstant::NULL);
            } else {
                $stmt->limit = $this->Expression();
            }

        } else {
            // SQL:2008 syntax
            $this->stream->expect(Token::TYPE_KEYWORD, 'fetch');
            $this->stream->expect(Token::TYPE_KEYWORD, ['first', 'next']);

            if ($this->stream->matchesKeyword(['row', 'rows'])) {
                // no limit specified -> 1 row
                $stmt->limit = new nodes\expressions\NumericConstant('1');
            } elseif ($this->stream->matchesSpecialChar(['+', '-'])) {
                // signed numeric constant: that case is not handled by ExpressionAtom()
                $sign = $this->stream->next();
                if ($this->stream->matches(Token::TYPE_FLOAT)) {
                    $constantToken = $this->stream->next();
                } else {
                    $constantToken = $this->stream->expect(Token::TYPE_INTEGER);
                }
                if ('+' === $sign->getValue()) {
                    $stmt->limit = nodes\expressions\Constant::createFromToken($constantToken);
                } else {
                    $stmt->limit = nodes\expressions\Constant::createFromToken(new Token(
                        $constantToken->getType(),
                        '-' . $constantToken->getValue(),
                        $constantToken->getPosition()
                    ));
                }
            } else {
                $stmt->limit = $this->ExpressionAtom();
            }

            $this->stream->expect(Token::TYPE_KEYWORD, ['row', 'rows']);
            if ($this->stream->matchesKeywordSequence('with', 'ties')) {
                $stmt->limitWithTies = true;
                $this->stream->skip(2);
            } else {
                $this->stream->expect(Token::TYPE_KEYWORD, 'only');
            }
        }
    }

    protected function OffsetClause(SelectCommon $stmt): void
    {
        if (null !== $stmt->offset) {
            throw exceptions\SyntaxException::atPosition(
                'Multiple OFFSET clauses are not allowed',
                $this->stream->getSource(),
                $this->stream->getCurrent()->getPosition()
            );
        }
        // NB: the following is a bit different from actual Postgres grammar, where offset only
        // allows c_expr (i.e. ExpressionAtom) production if trailed by ROW / ROWS, but full
        // a_expr (i.e. Expression) production in other case. We don't bother to do lookahead
        // here, so allow Expression in either case and treat trailing ROW / ROWS as noise
        $this->stream->expect(Token::TYPE_KEYWORD, 'offset');
        $stmt->offset = $this->Expression();
        if ($this->stream->matchesKeyword(['row', 'rows'])) {
            $this->stream->next();
        }
    }

    protected function SetClauseList(): nodes\lists\SetClauseList
    {
        $targetList = new nodes\lists\SetClauseList([$this->SetClause()]);
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $targetList[] = $this->SetClause();
        }
        return $targetList;
    }

    /**
     * @return nodes\MultipleSetClause|nodes\SingleSetClause
     */
    protected function SetClause()
    {
        if (!$this->stream->matchesSpecialChar('(')) {
            $column = $this->SetTargetElement();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '=');
            $value  = $this->ExpressionWithDefault();

            return new nodes\SingleSetClause($column, $value);

        } else {
            $this->stream->next();

            $columns = new nodes\lists\SetTargetList([$this->SetTargetElement()]);
            while ($this->stream->matchesSpecialChar(',')) {
                $this->stream->next();
                $columns[] = $this->SetTargetElement();
            }
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '=');

            $token = $this->stream->getCurrent();

            $value = $this->Expression();

            if (
                (!($value instanceof nodes\expressions\SubselectExpression) || $value->operator)
                && !($value instanceof nodes\expressions\RowExpression)
            ) {
                throw exceptions\SyntaxException::atPosition(
                    'source for a multiple-column UPDATE item must be a sub-SELECT or ROW() expression',
                    $this->stream->getSource(),
                    $token->getPosition()
                );
            }

            return new nodes\MultipleSetClause($columns, $value);
        }
    }

    protected function InsertTargetList(): nodes\lists\SetTargetList
    {
        $list = new nodes\lists\SetTargetList([$this->SetTargetElement()]);
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $list[] = $this->SetTargetElement();
        }
        return $list;
    }

    protected function SetTargetElement(): nodes\SetTargetElement
    {
        $colId       = $this->ColId();
        // The whole point of parameter to Indirection() is to prevent Star nodes from appearing in array
        /** @var array<nodes\Identifier|nodes\ArrayIndexes> $indirection */
        $indirection = $this->Indirection(false);
        return new nodes\SetTargetElement($colId, $indirection);
    }

    /**
     * @return nodes\ScalarExpression[]|nodes\SetToDefault[]
     */
    protected function ExpressionListWithDefault()
    {
        $values = [$this->ExpressionWithDefault()];
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $values[] = $this->ExpressionWithDefault();
        }

        return $values;
    }

    /**
     * @return nodes\ScalarExpression|nodes\SetToDefault
     */
    protected function ExpressionWithDefault()
    {
        if ($this->stream->matchesKeyword('default')) {
            $this->stream->next();
            return new nodes\SetToDefault();
        } else {
            return $this->Expression();
        }
    }

    protected function SelectWithParentheses(): SelectCommon
    {
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        $select = $this->SelectStatement();
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        return $select;
    }

    /**
     * SELECT ... [INTERSECT SELECT...]
     *
     */
    protected function SelectIntersect(): SelectCommon
    {
        $stmt = $this->SimpleSelect();

        while ($this->stream->matchesKeyword('intersect')) {
            $setOp = $this->stream->next()->getValue();
            if ($this->stream->matchesKeyword(['all', 'distinct'])) {
                $setOp .= ('all' === $this->stream->next()->getValue() ? ' all' : '');
            }
            $stmt = new SetOpSelect($stmt, $this->SimpleSelect(), $setOp);
        }

        return $stmt;
    }

    protected function SimpleSelect(): SelectCommon
    {
        if ($this->stream->matchesSpecialChar('(')) {
            return $this->SelectWithParentheses(); // select_with_parens grammar production
        }

        $token = $this->stream->expect(Token::TYPE_KEYWORD, ['select', 'values']);
        if ('values' === $token->getValue()) {
            return new Values($this->RowList());
        }

        $distinctClause = false;

        if ($this->stream->matchesKeyword('all')) {
            // noise "ALL"
            $this->stream->next();
        } elseif ($this->stream->matchesKeyword('distinct')) {
            $this->stream->next();
            if (!$this->stream->matchesKeyword('on')) {
                $distinctClause = true;
            } else {
                $this->stream->next();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
                $distinctClause = $this->ExpressionList();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }
        }

        $stmt = new Select($this->TargetList(), $distinctClause);

        if ($this->stream->matchesKeyword('into')) {
            throw new exceptions\NotImplementedException("SELECT INTO clauses are not supported");
        }

        if ($this->stream->matchesKeyword('from')) {
            $this->stream->next();
            $stmt->from->replace($this->FromList());
        }

        if ($this->stream->matchesKeyword('where')) {
            $this->stream->next();
            $stmt->where->condition = $this->Expression();
        }

        if ($this->stream->matchesKeywordSequence('group', 'by')) {
            $this->stream->skip(2);
            $stmt->group->replace($this->GroupByClause());
        }

        if ($this->stream->matchesKeyword('having')) {
            $this->stream->next();
            $stmt->having->condition = $this->Expression();
        }

        if ($this->stream->matchesKeyword('window')) {
            $this->stream->next();
            $stmt->window->replace($this->WindowList());
        }

        return $stmt;
    }

    protected function WindowList(): nodes\lists\WindowList
    {
        $windows = new nodes\lists\WindowList([$this->WindowDefinition()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $windows[] = $this->WindowDefinition();
        }

        return $windows;
    }

    protected function WindowDefinition(): nodes\WindowDefinition
    {
        $name    = $this->ColId();
        $this->stream->expect(Token::TYPE_KEYWORD, 'as');
        $spec    = $this->WindowSpecification();
        $spec->setName($name);

        return $spec;
    }

    protected function WindowSpecification(): nodes\WindowDefinition
    {
        $refName = $partition = $frame = $order = null;
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        if (
            $this->stream->matchesAnyType(Token::TYPE_IDENTIFIER, Token::TYPE_COL_NAME_KEYWORD)
            || ($this->stream->matches(Token::TYPE_UNRESERVED_KEYWORD)
                // See comment for opt_existing_window_name production in gram.y
                && !in_array($this->stream->getCurrent()->getValue(), ['partition', 'range', 'rows', 'groups']))
        ) {
            $refName = $this->ColId();
        }
        if ($this->stream->matchesKeywordSequence('partition', 'by')) {
            $this->stream->skip(2);
            $partition = $this->ExpressionList();
        }
        if ($this->stream->matchesKeywordSequence('order', 'by')) {
            $this->stream->skip(2);
            $order = $this->OrderByList();
        }
        if ($this->stream->matchesKeyword(['range', 'rows', 'groups'])) {
            $frame = $this->WindowFrameClause();
        }

        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        return new nodes\WindowDefinition($refName, $partition, $order, $frame);
    }

    protected function WindowFrameClause(): nodes\WindowFrameClause
    {
        $frameType  = $this->stream->expect(Token::TYPE_KEYWORD, ['range', 'rows', 'groups']);
        $tokenStart = $this->stream->getCurrent();
        $exclusion  = null;
        if (!$tokenStart->matches(Token::TYPE_KEYWORD, 'between')) {
            $start = $this->WindowFrameBound();
            $end   = null;

        } else {
            $this->stream->next();
            $start = $this->WindowFrameBound();
            $this->stream->expect(Token::TYPE_KEYWORD, 'and');
            $end   = $this->WindowFrameBound();
        }

        // opt_window_exclusion_clause from gram.y
        if ($this->stream->matchesKeyword('exclude')) {
            $this->stream->next();
            $first = $this->stream->expect(Token::TYPE_KEYWORD, ['current', 'group', 'ties', 'no']);
            switch ($first->getValue()) {
                case 'current':
                    $this->stream->expect(Token::TYPE_KEYWORD, 'row');
                    $exclusion = 'current row';
                    break;
                case 'no':
                    $this->stream->expect(Token::TYPE_KEYWORD, 'others');
                    // EXCLUDE NO OTHERS is noise
                    break;
                default:
                    $exclusion = $first->getValue();
            }
        }

        // Repackage exceptions thrown in WindowFrameClause constructor as syntax ones and provide context
        try {
            return new nodes\WindowFrameClause($frameType->getValue(), $start, $end, $exclusion);

        } catch (exceptions\InvalidArgumentException $e) {
            throw exceptions\SyntaxException::atPosition(
                $e->getMessage(),
                $this->stream->getSource(),
                $tokenStart->getPosition()
            );
        }
    }

    protected function WindowFrameBound(): nodes\WindowFrameBound
    {
        foreach (self::CHECKS_FRAME_BOUND as $check) {
            if ($this->stream->matchesKeywordSequence(...$check)) {
                $this->stream->skip(2);
                return new nodes\WindowFrameBound('current' === $check[0] ? 'current row' : $check[1]);
            }
        }

        $value     = $this->Expression();
        $direction = $this->stream->expect(Token::TYPE_KEYWORD, ['preceding', 'following'])->getValue();
        return new nodes\WindowFrameBound($direction, $value);
    }

    protected function ExpressionList(): nodes\lists\ExpressionList
    {
        $expressions = new nodes\lists\ExpressionList([$this->Expression()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $expressions[] = $this->Expression();
        }

        return $expressions;
    }

    protected function Expression(): nodes\ScalarExpression
    {
        $terms = [$this->LogicalExpressionTerm()];

        while ($this->stream->matchesKeyword('or')) {
            $this->stream->next();

            $terms[] = $this->LogicalExpressionTerm();
        }

        if (1 === count($terms)) {
            return $terms[0];
        }

        return new nodes\expressions\LogicalExpression($terms, 'or');
    }

    protected function LogicalExpressionTerm(): nodes\ScalarExpression
    {
        $factors = [$this->LogicalExpressionFactor()];

        while ($this->stream->matchesKeyword('and')) {
            $this->stream->next();

            $factors[] = $this->LogicalExpressionFactor();
        }

        if (1 === count($factors)) {
            return $factors[0];
        }

        return new nodes\expressions\LogicalExpression($factors, 'and');
    }

    protected function LogicalExpressionFactor(): nodes\ScalarExpression
    {
        if ($this->stream->matchesKeyword('not')) {
            $this->stream->next();
            return new nodes\expressions\NotExpression($this->LogicalExpressionFactor());
        }
        return $this->IsWhateverExpression();
    }

    /**
     * In Postgres 9.5+ all comparison operators have the same precedence and are non-associative
     *
     * @param bool $restricted
     * @return nodes\ScalarExpression
     */
    protected function Comparison(bool $restricted = false): nodes\ScalarExpression
    {
        $argument = $restricted
                    ? $this->GenericOperatorExpression(true)
                    : $this->PatternMatchingExpression();

        if (
            $this->stream->matchesSpecialChar(['<', '>', '='])
            || $this->stream->matches(Token::TYPE_INEQUALITY)
        ) {
            return new nodes\expressions\OperatorExpression(
                $this->stream->next()->getValue(),
                $argument,
                $restricted ? $this->GenericOperatorExpression(true) : $this->PatternMatchingExpression()
            );
        }

        return $argument;
    }

    protected function PatternMatchingExpression(): nodes\ScalarExpression
    {
        $string = $this->OverlapsExpression();

        // speedup
        if (!$this->stream->matchesKeyword(['like', 'ilike', 'not', 'similar'])) {
            return $string;
        }

        foreach (self::CHECKS_PATTERN_MATCHING as $checkIdx => $check) {
            if ($this->stream->matchesKeywordSequence(...$check)) {
                $this->stream->skip(count($check));

                $escape = null;
                if ($checkIdx < 4 && $this->stream->matchesKeyword(self::SUBQUERY_EXPRESSIONS)) {
                    $pattern = $this->SubqueryExpression();

                } else {
                    $pattern = $this->OverlapsExpression();
                    if ($this->stream->matchesKeyword('escape')) {
                        $this->stream->next();
                        $escape = $this->OverlapsExpression();
                    }
                }

                if ('not' !== $check[0]) {
                    $negated = false;
                } else {
                    array_shift($check);
                    $negated = true;
                }

                return new nodes\expressions\PatternMatchingExpression(
                    $string,
                    $pattern,
                    implode(' ', $check),
                    $negated,
                    $escape
                );
            }
        }

        return $string;
    }

    protected function SubqueryExpression(): nodes\ScalarExpression
    {
        $type  = $this->stream->expect(Token::TYPE_KEYWORD, self::SUBQUERY_EXPRESSIONS)->getValue();
        $check = $this->checkContentsOfParentheses();

        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        if (self::PARENTHESES_SELECT === $check) {
            $result = new nodes\expressions\SubselectExpression($this->SelectStatement(), $type);
        } else {
            $result = new nodes\expressions\ArrayComparisonExpression($type, $this->Expression());
        }
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        return $result;
    }

    protected function OverlapsExpression(): nodes\ScalarExpression
    {
        $left = $this->BetweenExpression();

        if (
            !$left instanceof nodes\expressions\RowExpression
            || !$this->stream->matchesKeyword('overlaps')
        ) {
            return $left;
        }

        $token = $this->stream->next();
        $right = $this->RowConstructor();

        try {
            return new nodes\expressions\OverlapsExpression($left, $right);
        } catch (exceptions\InvalidArgumentException $e) {
            throw exceptions\SyntaxException::atPosition(
                $e->getMessage(),
                $this->stream->getSource(),
                $token->getPosition()
            );
        }
    }

    /**
     * Parses BETWEEN expressions
     *
     * Note that in pre-9.5 Postgres BETWEEN expressions were de-facto left-associative, so that
     * <code>
     * select 1 between 0 and 2 between false and true;
     * </code>
     * actually worked. Expressions of the form
     * <code>
     * select 2 between 3 and 4 is false;
     * </code>
     * resulted in syntax error but work in 9.5+
     *
     * @return nodes\ScalarExpression
     */
    protected function BetweenExpression(): nodes\ScalarExpression
    {
        $value = $this->InExpression();

        if (!$this->stream->matchesKeyword(['between', 'not'])) {
            return $value;
        }

        if ('between' === ($this->stream->getCurrent()->getValue())) {
            $negated = false;
            $this->stream->next();
        } elseif (!$this->stream->look(1)->matches(Token::TYPE_KEYWORD, 'between')) {
            return $value;
        } else {
            $negated = true;
            $this->stream->skip(2);
        }
        $operator = 'between';
        if ($this->stream->matchesKeyword(['symmetric', 'asymmetric'])) {
            $operator .= ' ' . $this->stream->next()->getValue();
        }

        $left  = $this->GenericOperatorExpression(true);
        $this->stream->expect(Token::TYPE_KEYWORD, 'and');
        // right argument of BETWEEN is defined as 'b_expr' in pre-9.5 grammar and as 'a_expr' afterwards
        $right = $this->GenericOperatorExpression(false);

        return new nodes\expressions\BetweenExpression($value, $left, $right, $operator, $negated);
    }

    protected function RestrictedExpression(): nodes\ScalarExpression
    {
        return $this->Comparison(true);
    }

    /**
     * Parses [NOT ] IN (...) expression
     *
     * The query
     * <code>
     * select 'foo' in ('foo', 'bar') in (true, false);
     * </code>
     * actually works, so we allow it here. That's pretty strange as [NOT ] IN is defined
     * %nonassoc in gram.y and the above code should be a syntax error per bison docs:
     * > %nonassoc specifies no associativity, which means that `x op y op z' is considered a syntax error.
     */
    protected function InExpression(): nodes\ScalarExpression
    {
        $left = $this->GenericOperatorExpression();

        while ($this->stream->matchesKeyword(['not', 'in'])) {
            if ('in' === $this->stream->getCurrent()->getValue()) {
                $negated = false;
                $this->stream->next();
            } elseif (!$this->stream->look(1)->matches(Token::TYPE_KEYWORD, 'in')) {
                break;
            } else {
                $negated = true;
                $this->stream->skip(2);
            }

            $check = $this->checkContentsOfParentheses();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $right = self::PARENTHESES_SELECT === $check ? $this->SelectStatement() : $this->ExpressionList();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

            $left = new nodes\expressions\InExpression($left, $right, $negated);
        }

        return $left;
    }


    /**
     * Handles infix and postfix operators
     *
     * @param bool $restricted
     * @return nodes\ScalarExpression
     */
    protected function GenericOperatorExpression(bool $restricted = false): nodes\ScalarExpression
    {
        $leftOperand = $this->GenericOperatorTerm($restricted);

        while (
            ($op = $this->matchesOperator())
               || $this->stream->matches(Token::TYPE_SPECIAL, self::MATH_OPERATORS)
                   && $this->stream->look(1)->matches(Token::TYPE_KEYWORD, self::SUBQUERY_EXPRESSIONS)
        ) {
            $operator = $op ? $this->Operator() : $this->stream->next()->getValue();
            if (!$op || $this->stream->matchesKeyword(self::SUBQUERY_EXPRESSIONS)) {
                // subquery operator
                $leftOperand = new nodes\expressions\OperatorExpression(
                    $operator,
                    $leftOperand,
                    $this->SubqueryExpression()
                );
            } else {
                $leftOperand = new nodes\expressions\OperatorExpression(
                    $operator,
                    $leftOperand,
                    $this->GenericOperatorTerm($restricted)
                );
            }
        }

        return $leftOperand;
    }

    protected function GenericOperatorTerm(bool $restricted = false): nodes\ScalarExpression
    {
        $operators = [];
        // prefix operator(s)
        while ($this->matchesOperator()) {
            $operators[] = $this->Operator();
        }
        $term = $this->ArithmeticExpression($restricted);
        // prefix operators are left-associative
        while (!empty($operators)) {
            $term = new nodes\expressions\OperatorExpression(array_pop($operators), null, $term);
        }

        return $term;
    }

    /**
     * @param bool $all Whether to match qual_Op or qual_all_Op production
     *                  (the latter allows mathematical operators)
     * @return string|nodes\QualifiedOperator
     */
    protected function Operator(bool $all = false)
    {
        if (
            $this->stream->matches(Token::TYPE_OPERATOR)
            || $all && $this->stream->matches(Token::TYPE_SPECIAL, self::MATH_OPERATORS)
        ) {
            return $this->stream->next()->getValue();
        }

        $this->stream->expect(Token::TYPE_KEYWORD, 'operator');
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        $parts = [];
        while (
            $this->stream->matchesAnyType(
                Token::TYPE_IDENTIFIER,
                Token::TYPE_UNRESERVED_KEYWORD,
                Token::TYPE_COL_NAME_KEYWORD
            )
        ) {
            // ColId
            $parts[] = nodes\Identifier::createFromToken($this->stream->next());
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '.');
        }
        if ($this->stream->matches(Token::TYPE_SPECIAL, self::MATH_OPERATORS)) {
            $parts[] = $this->stream->next()->getValue();
        } else {
            $parts[] = $this->stream->expect(Token::TYPE_OPERATOR)->getValue();
        }
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        return new nodes\QualifiedOperator(...$parts);
    }

    protected function IsWhateverExpression(bool $restricted = false): nodes\ScalarExpression
    {
        $operand = $this->Comparison($restricted);
        $isNot   = false;
        $checks  = array_merge(
            $restricted ? [] : self::CHECKS_IS_WHATEVER,
            [['document']]
        );

        while (
            $this->stream->matchesKeyword('is')
               || !$restricted && $this->stream->matchesKeyword(['notnull', 'isnull'])
        ) {
            $operator = $this->stream->next()->getValue();
            if ('notnull' === $operator) {
                $operand = new nodes\expressions\IsExpression($operand, nodes\expressions\IsExpression::NULL, true);
                continue;
            } elseif ('isnull' === $operator) {
                $operand = new nodes\expressions\IsExpression($operand, nodes\expressions\IsExpression::NULL);
                continue;
            }

            if ($this->stream->matchesKeyword('not')) {
                $this->stream->next();
                $isNot = true;
            }

            foreach ($checks as $check) {
                if ($this->stream->matchesKeywordSequence(...$check)) {
                    $isOperator = [];
                    for ($i = 0; $i < count($check); $i++) {
                        $isOperator[] = $this->stream->next()->getValue();
                    }
                    $operand = new nodes\expressions\IsExpression($operand, implode(' ', $isOperator), $isNot);
                    continue 2;
                }
            }

            if ($this->stream->matchesKeywordSequence('distinct', 'from')) {
                $this->stream->skip(2);
                return new nodes\expressions\IsDistinctFromExpression(
                    $operand,
                    $this->ArithmeticExpression($restricted),
                    $isNot
                );
            }

            throw new exceptions\SyntaxException('Unexpected ' . $this->stream->getCurrent());
        }

        return $operand;
    }

    protected function TypeName(): nodes\TypeName
    {
        $setOf  = false;
        $bounds = [];
        if ($this->stream->matchesKeyword('setof')) {
            $this->stream->next();
            $setOf = true;
        }

        $typeName = $this->SimpleTypeName();
        $typeName->setSetOf($setOf);

        if ($this->stream->matchesKeyword('array')) {
            $this->stream->next();
            if (!$this->stream->matchesSpecialChar('[')) {
                $bounds[] = -1;
            } else {
                $this->stream->next();
                $bounds[] = $this->stream->expect(Token::TYPE_INTEGER)->getValue();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ']');
            }

        } else {
            while ($this->stream->matchesSpecialChar('[')) {
                $this->stream->next();
                $bounds[] = $this->stream->matches(Token::TYPE_INTEGER) ? $this->stream->next()->getValue() : -1;
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ']');
            }
        }
        $typeName->setBounds($bounds);

        return $typeName;
    }

    protected function SimpleTypeName(): nodes\TypeName
    {
        $typeName = $this->IntervalTypeName()
                    ?? $this->DateTimeTypeName()
                    ?? $this->CharacterTypeName()
                    ?? $this->BitTypeName()
                    ?? $this->NumericTypeName()
                    ?? $this->GenericTypeName();

        if (null !== $typeName) {
            return $typeName;
        }

        throw exceptions\SyntaxException::atPosition(
            'Expecting type name',
            $this->stream->getSource(),
            $this->stream->getCurrent()->getPosition()
        );
    }

    protected function NumericTypeName(): ?nodes\TypeName
    {
        if (
            !$this->stream->matchesKeyword(self::STANDARD_TYPES_NUMERIC)
            || ('double' === $this->stream->getCurrent()->getValue()
                && !$this->stream->look()->matches(Token::TYPE_KEYWORD, 'precision'))
        ) {
            return null;
        }

        $typeName  = $this->stream->next()->getValue();
        $modifiers = null;
        if ('double' === $typeName) {
            // "double precision"
            $typeName .= ' ' . $this->stream->next()->getValue();

        } elseif ('float' === $typeName) {
            $floatName = 'float8';
            if ($this->stream->matchesSpecialChar('(')) {
                $this->stream->next();
                $precisionToken = $this->stream->expect(Token::TYPE_INTEGER);
                $precision      = $precisionToken->getValue();
                if ($precision < 1) {
                    throw exceptions\SyntaxException::atPosition(
                        'Precision for type float must be at least 1 bit',
                        $this->stream->getSource(),
                        $precisionToken->getPosition()
                    );
                } elseif ($precision <= 24) {
                    $floatName = 'float4';
                } elseif ($precision <= 53) {
                    $floatName = 'float8';
                } else {
                    throw exceptions\SyntaxException::atPosition(
                        'Precision for type float must be less than 54 bits',
                        $this->stream->getSource(),
                        $precisionToken->getPosition()
                    );
                }
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }
            return new nodes\TypeName(new nodes\QualifiedName('pg_catalog', $floatName));

        } elseif ('decimal' === $typeName || 'dec' === $typeName || 'numeric' === $typeName) {
            // NB: we explicitly require constants here, per comment in gram.y:
            // > To avoid parsing conflicts against function invocations, the modifiers
            // > have to be shown as expr_list here, but parse analysis will only accept
            // > constants for them.
            if ($this->stream->matchesSpecialChar('(')) {
                $this->stream->next();
                $modifiers = new nodes\lists\TypeModifierList([
                    nodes\expressions\Constant::createFromToken($this->stream->expect(Token::TYPE_INTEGER))
                ]);
                if ($this->stream->matchesSpecialChar(',')) {
                    $this->stream->next();
                    $modifiers[] = nodes\expressions\Constant::createFromToken(
                        $this->stream->expect(Token::TYPE_INTEGER)
                    );
                }
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }
        }

        return new nodes\TypeName(
            new nodes\QualifiedName('pg_catalog', self::STANDARD_TYPES_MAPPING[$typeName]),
            $modifiers
        );
    }

    protected function BitTypeName(bool $leading = false): ?nodes\TypeName
    {
        if (!$this->stream->matchesKeyword(self::STANDARD_TYPES_BIT)) {
            return null;
        }

        $typeName  = $this->stream->next()->getValue();
        $modifiers = null;
        if ($this->stream->matchesKeyword('varying')) {
            $this->stream->next();
            $typeName = 'varbit';
        }
        if ($this->stream->matchesSpecialChar('(')) {
            $this->stream->next();
            $modifiers = new nodes\lists\TypeModifierList([
                nodes\expressions\Constant::createFromToken($this->stream->expect(Token::TYPE_INTEGER))
            ]);
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        }
        // BIT translates to bit(1) *unless* this is a leading typecast
        // where it translates to "any length" (with no modifiers)
        if (!$leading && $typeName === 'bit' && empty($modifiers)) {
            $modifiers = new nodes\lists\TypeModifierList([new nodes\expressions\NumericConstant('1')]);
        }
        return new nodes\TypeName(
            new nodes\QualifiedName('pg_catalog', $typeName),
            $modifiers
        );
    }

    protected function CharacterTypeName(bool $leading = false): ?nodes\TypeName
    {
        if (
            !$this->stream->matchesKeyword(self::STANDARD_TYPES_CHARACTER)
            || ('national' === $this->stream->getCurrent()->getValue()
                && !$this->stream->look(1)->matches(Token::TYPE_KEYWORD, ['character', 'char']))
        ) {
            return null;
        }

        $typeName  = $this->stream->next()->getValue();
        $varying   = ('varchar' === $typeName);
        $modifiers = null;
        if ('national' === $typeName) {
            $this->stream->next();
        }
        if ('varchar' !== $typeName && $this->stream->matchesKeyword('varying')) {
            $this->stream->next();
            $varying = true;
        }
        if ($this->stream->matchesSpecialChar('(')) {
            $this->stream->next();
            $modifiers = new nodes\lists\TypeModifierList([
                nodes\expressions\Constant::createFromToken($this->stream->expect(Token::TYPE_INTEGER))
            ]);
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        }
        // CHAR translates to char(1) *unless* this is a leading typecast
        // where it translates to "any length" (with no modifiers)
        if (!$leading && !$varying && null === $modifiers) {
            $modifiers = new nodes\lists\TypeModifierList([new nodes\expressions\NumericConstant('1')]);
        }

        return new nodes\TypeName(
            new nodes\QualifiedName('pg_catalog', $varying ? 'varchar' : 'bpchar'),
            $modifiers
        );
    }

    protected function DateTimeTypeName(): ?nodes\TypeName
    {
        if (!$this->stream->matchesKeyword(self::STANDARD_TYPES_DATETIME)) {
            return null;
        }

        $typeName  = $this->stream->next()->getValue();
        $modifiers = null;
        if ($this->stream->matchesSpecialChar('(')) {
            $this->stream->next();
            $modifiers = new nodes\lists\TypeModifierList([
                nodes\expressions\Constant::createFromToken($this->stream->expect(Token::TYPE_INTEGER))
            ]);
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        }

        if ($this->stream->matchesKeywordSequence(['with', 'without'], 'time', 'zone')) {
            if ('with' === $this->stream->next()->getValue()) {
                $typeName .= 'tz';
            }
            $this->stream->skip(2);
        }

        return new nodes\TypeName(new nodes\QualifiedName('pg_catalog', $typeName), $modifiers);
    }

    protected function IntervalTypeName(): ?nodes\IntervalTypeName
    {
        if (!$this->stream->matchesKeyword('interval')) {
            return null;
        }
        $this->stream->next();

        $modifiers = $this->IntervalTypeModifiers();

        return $this->IntervalWithPossibleTrailingTypeModifiers($modifiers);
    }

    protected function IntervalLeadingTypecast(): ?nodes\expressions\TypecastExpression
    {
        if (!$this->stream->matchesKeyword('interval')) {
            return null;
        }
        $this->stream->next();

        $modifiers = $this->IntervalTypeModifiers();
        $operand   = nodes\expressions\Constant::createFromToken($this->stream->expect(Token::TYPE_STRING));
        $typeNode  = $this->IntervalWithPossibleTrailingTypeModifiers($modifiers);

        return new nodes\expressions\TypecastExpression($operand, $typeNode);
    }

    protected function IntervalWithPossibleTrailingTypeModifiers(
        nodes\lists\TypeModifierList $modifiers = null
    ): nodes\IntervalTypeName {
        if (
            null === $modifiers
            && $this->stream->matchesKeyword(['year', 'month', 'day', 'hour', 'minute', 'second'])
        ) {
            $trailing  = [$this->stream->next()->getValue()];
            $second    = 'second' === $trailing[0];
            if ($this->stream->matchesKeyword('to')) {
                $toToken    = $this->stream->next();
                $trailing[] = 'to';
                if ('year' === $trailing[0]) {
                    $end = $this->stream->expect(Token::TYPE_KEYWORD, 'month');
                } elseif ('day' === $trailing[0]) {
                    $end = $this->stream->expect(Token::TYPE_KEYWORD, ['hour', 'minute', 'second']);
                } elseif ('hour' === $trailing[0]) {
                    $end = $this->stream->expect(Token::TYPE_KEYWORD, ['minute', 'second']);
                } elseif ('minute' === $trailing[0]) {
                    $end = $this->stream->expect(Token::TYPE_KEYWORD, 'second');
                } else {
                    throw new exceptions\SyntaxException('Unexpected ' . $toToken);
                }
                $second     = 'second' === $end->getValue();
                $trailing[] = $end->getValue();
            }

            if ($second) {
                $modifiers = $this->IntervalTypeModifiers();
            }
        }
        $typeNode = new nodes\IntervalTypeName($modifiers);
        if (!empty($trailing)) {
            $typeNode->setMask(implode(' ', $trailing));
        }

        return $typeNode;
    }

    protected function IntervalTypeModifiers(): ?nodes\lists\TypeModifierList
    {
        if (!$this->stream->matchesSpecialChar('(')) {
            $modifiers = null;
        } else {
            $this->stream->next();
            $modifiers = new nodes\lists\TypeModifierList([
                nodes\expressions\Constant::createFromToken($this->stream->expect(Token::TYPE_INTEGER))
            ]);
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        }
        return $modifiers;
    }

    protected function GenericTypeName(): ?nodes\TypeName
    {
        if (
            !$this->stream->matchesAnyType(
                Token::TYPE_IDENTIFIER,
                Token::TYPE_UNRESERVED_KEYWORD,
                Token::TYPE_TYPE_FUNC_NAME_KEYWORD
            )
        ) {
            return null;
        }

        $typeName = [nodes\Identifier::createFromToken($this->stream->next())];
        while ($this->stream->matchesSpecialChar('.')) {
            $this->stream->next();
            if ($this->stream->matches(Token::TYPE_IDENTIFIER)) {
                $typeName[] = nodes\Identifier::createFromToken($this->stream->next());
            } else {
                // any keyword goes, see ColLabel
                $typeName[] = nodes\Identifier::createFromToken($this->stream->expect(Token::TYPE_KEYWORD));
            }
        }

        return new nodes\TypeName(new nodes\QualifiedName(...$typeName), $this->GenericTypeModifierList());
    }

    protected function GenericTypeModifierList(): ?nodes\lists\TypeModifierList
    {
        if (!$this->stream->matchesSpecialChar('(')) {
            return null;
        }

        $this->stream->next();
        $modifiers = new nodes\lists\TypeModifierList([$this->GenericTypeModifier()]);
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $modifiers[] = $this->GenericTypeModifier();
        }
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        return $modifiers;
    }

    /**
     * Gets a type modifier for a "generic" type
     *
     * Type modifiers here are allowed according to typenameTypeMod() function from
     * src/backend/parser/parse_type.c
     *
     * @return nodes\expressions\Constant|nodes\Identifier
     * @throws exceptions\SyntaxException
     */
    protected function GenericTypeModifier()
    {
        // Let's keep most common case at the top
        if ($this->stream->matchesAnyType(Token::TYPE_INTEGER, Token::TYPE_FLOAT, Token::TYPE_STRING)) {
            return nodes\expressions\Constant::createFromToken($this->stream->next());

        } elseif (
            $this->stream->matchesAnyType(
                Token::TYPE_IDENTIFIER,
                Token::TYPE_UNRESERVED_KEYWORD,
                Token::TYPE_TYPE_FUNC_NAME_KEYWORD
            )
        ) {
            // allows ColId
            return nodes\Identifier::createFromToken($this->stream->next());

        } else {
            throw new exceptions\SyntaxException(
                "Expecting a constant or an identifier, got " . $this->stream->getCurrent()
            );
        }
    }

    protected function ArithmeticExpression(bool $restricted = false): nodes\ScalarExpression
    {
        $leftOperand = $this->ArithmeticTerm($restricted);

        while ($this->stream->matchesSpecialChar(['+', '-'])) {
            $operator = $this->stream->next()->getValue();
            $leftOperand = new nodes\expressions\OperatorExpression(
                $operator,
                $leftOperand,
                $this->ArithmeticTerm($restricted)
            );
        }

        return $leftOperand;
    }

    protected function ArithmeticTerm(bool $restricted = false): nodes\ScalarExpression
    {
        $leftOperand = $this->ArithmeticMultiplier($restricted);

        while ($this->stream->matchesSpecialChar(['*', '/', '%'])) {
            $operator = $this->stream->next()->getValue();
            $leftOperand = new nodes\expressions\OperatorExpression(
                $operator,
                $leftOperand,
                $this->ArithmeticMultiplier($restricted)
            );
        }

        return $leftOperand;
    }

    protected function ArithmeticMultiplier(bool $restricted = false): nodes\ScalarExpression
    {
        $leftOperand = $restricted
                       ? $this->UnaryPlusMinusExpression()
                       : $this->AtTimeZoneExpression();

        while ($this->stream->matchesSpecialChar('^')) {
            $operator    = $this->stream->next()->getValue();
            $leftOperand = new nodes\expressions\OperatorExpression(
                $operator,
                $leftOperand,
                $restricted ? $this->UnaryPlusMinusExpression() : $this->AtTimeZoneExpression()
            );
        }

        return $leftOperand;
    }

    protected function AtTimeZoneExpression(): nodes\ScalarExpression
    {
        $left = $this->CollateExpression();
        if ($this->stream->matchesKeywordSequence('at', 'time', 'zone')) {
            $this->stream->skip(3);
            return new nodes\expressions\AtTimeZoneExpression($left, $this->CollateExpression());
        }
        return $left;
    }

    protected function CollateExpression(): nodes\ScalarExpression
    {
        $left = $this->UnaryPlusMinusExpression();
        if ($this->stream->matchesKeyword('collate')) {
            $this->stream->next();
            return new nodes\expressions\CollateExpression($left, $this->QualifiedName());
        }
        return $left;
    }

    protected function UnaryPlusMinusExpression(): nodes\ScalarExpression
    {
        if (!$this->stream->matchesSpecialChar(['+', '-'])) {
            return $this->TypecastExpression();
        }

        $token    = $this->stream->next();
        $operator = $token->getValue();
        $operand  = $this->UnaryPlusMinusExpression();
        if (!$operand instanceof nodes\expressions\NumericConstant || '-' !== $operator) {
            return new nodes\expressions\OperatorExpression($operator, null, $operand);
        } elseif ('-' === $operand->value[0]) {
            return new nodes\expressions\NumericConstant(substr($operand->value, 1));
        } else {
            return new nodes\expressions\NumericConstant('-' . $operand->value);
        }
    }

    protected function TypecastExpression(): nodes\ScalarExpression
    {
        $left = $this->ExpressionAtom();

        while ($this->stream->matches(Token::TYPE_TYPECAST)) {
            $this->stream->next();
            $left = new nodes\expressions\TypecastExpression($left, $this->TypeName());
        }

        return $left;
    }

    protected function ExpressionAtom(): nodes\ScalarExpression
    {
        $token = $this->stream->getCurrent();
        if ($this->stream->matchesKeyword(self::ATOM_KEYWORDS)) {
            switch ($token->getValue()) {
                case 'row':
                    if ($this->stream->look()->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                        return $this->RowConstructor();
                    }
                    break;

                case 'array':
                    return $this->ArrayConstructor();

                case 'exists':
                    $this->stream->next();
                    return new nodes\expressions\SubselectExpression($this->SelectWithParentheses(), 'exists');

                case 'case':
                    return $this->CaseExpression();

                case 'grouping':
                    return $this->GroupingExpression();

                case 'true':
                case 'false':
                case 'null':
                    return nodes\expressions\Constant::createFromToken($this->stream->next());
            }

        } elseif (0 !== ($token->getType() & self::ATOM_SPECIAL_TYPES)) {
            if ($token->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                switch ($this->checkContentsOfParentheses()) {
                    case self::PARENTHESES_ROW:
                        return $this->RowConstructor();

                    case self::PARENTHESES_SELECT:
                        $atom = new nodes\expressions\SubselectExpression($this->SelectWithParentheses());
                        break;

                    case self::PARENTHESES_EXPRESSION:
                    default:
                        $this->stream->next();
                        $atom = $this->Expression();
                        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
                }

            } elseif ($token->matches(Token::TYPE_PARAMETER)) {
                $atom = nodes\expressions\Parameter::createFromToken($this->stream->next());

            } elseif ($token->matches(Token::TYPE_LITERAL)) {
                return nodes\expressions\Constant::createFromToken($this->stream->next());
            }
        }

        if (!isset($atom)) {
            if ($this->matchesConstTypecast()) {
                return $this->ConstLeadingTypecast();

            } elseif ($this->matchesSpecialFunctionCall() && null !== ($function = $this->SpecialFunctionCall())) {
                return $this->convertSpecialFunctionCallToFunctionExpression($function);

            } else {
                // By the time we got here everything that can still legitimately be matched should
                // start with a (potentially qualified) name. To prevent back-and-forth match()ing and look()ing
                // the NamedExpressionAtom() matches such name as far as possible
                // and then passes the matched parts for further processing if expression looks legit.
                return $this->NamedExpressionAtom();
            }
        }

        if ([] !== ($indirection = $this->Indirection())) {
            return new nodes\Indirection($indirection, $atom);
        }

        return $atom;
    }

    /**
     * Represents AexprConst production from the grammar, used only in CYCLE clause currently
     *
     * @return nodes\expressions\Constant|nodes\expressions\ConstantTypecastExpression
     */
    public function ConstantExpression(): nodes\ScalarExpression
    {
        if (
            $this->stream->matchesKeyword(['true', 'false', 'null'])
            || $this->stream->matches(Token::TYPE_LITERAL)
        ) {
            return nodes\expressions\Constant::createFromToken($this->stream->next());
        }
        if ($this->matchesConstTypecast()) {
            $typecast = $this->ConstLeadingTypecast();
        } else {
            // We are only interested in typecasts here, everything else is an error according to AexprConst
            $token    = $this->stream->getCurrent();
            $typecast = $this->NamedExpressionAtom();
            if (!$typecast instanceof nodes\expressions\TypecastExpression) {
                throw exceptions\SyntaxException::atPosition(
                    "Unexpected {$token}, expecting constant expression",
                    $this->stream->getSource(),
                    $token->getPosition()
                );
            }
        }
        return new nodes\expressions\ConstantTypecastExpression(clone $typecast->argument, clone $typecast->type);
    }

    protected function NamedExpressionAtom(): nodes\ScalarExpression
    {
        $token       = $this->stream->getCurrent();
        $firstType   = $token->getType();

        // This will throw an Exception if current token in stream cannot start a name
        if (!isset(self::ATOM_IDENTIFIER_TYPES[$firstType])) {
            $this->stream->expect(Token::TYPE_IDENTIFIER);
        }

        $identifiers = [nodes\Identifier::createFromToken($token)];

        $lookIdx = 1;
        while ($this->stream->look($lookIdx)->matches(Token::TYPE_SPECIAL_CHAR, '.')) {
            $token = $this->stream->look($lookIdx + 1);
            if (!$token->matches(Token::TYPE_IDENTIFIER) && !$token->matches(Token::TYPE_KEYWORD)) {
                break;
            }
            $identifiers[]  = nodes\Identifier::createFromToken($token);
            $lookIdx       += 2;
        }

        if (
            // check that whatever we got looks like func_name production
            !(1 === count($identifiers) && Token::TYPE_COL_NAME_KEYWORD === $firstType
              || 1 < count($identifiers) && Token::TYPE_TYPE_FUNC_NAME_KEYWORD === $firstType)
        ) {
            if ($this->stream->look($lookIdx)->matches(Token::TYPE_STRING)) {
                $this->stream->skip($lookIdx);
                return $this->GenericLeadingTypecast($identifiers);
            } elseif ($this->stream->look($lookIdx)->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                $this->stream->skip($lookIdx);
                if ($this->stream->look(1)->matches(Token::TYPE_SPECIAL_CHAR, ')')) {
                    return $this->FunctionExpression($identifiers);
                } else {
                    $beyond = $this->skipParentheses(0);
                    return $this->stream->look($beyond)->matches(Token::TYPE_STRING)
                           ? $this->GenericLeadingTypecast($identifiers)
                           : $this->FunctionExpression($identifiers);
                }
            }
        }

        // This will throw an exception if matched name is an invalid ColumnReference
        if (Token::TYPE_TYPE_FUNC_NAME_KEYWORD === $firstType) {
            $this->stream->expect(Token::TYPE_IDENTIFIER);
        }

        $this->stream->skip($lookIdx);
        return $this->ColumnReference($identifiers);
    }

    protected function RowList(): nodes\lists\RowList
    {
        $list = new nodes\lists\RowList([$this->RowConstructorNoKeyword()]);
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $list[] = $this->RowConstructorNoKeyword();
        }
        return $list;
    }

    protected function RowConstructor(): nodes\expressions\RowExpression
    {
        if ($this->stream->matchesKeyword('row')) {
            $this->stream->next();
        }
        // ROW() is only possible with the keyword, 'VALUES ()' is a syntax error
        if (
            $this->stream->matchesSpecialChar('(')
            && $this->stream->look()->matches(Token::TYPE_SPECIAL_CHAR, ')')
        ) {
            $this->stream->skip(2);
            return new nodes\expressions\RowExpression([]);
        }

        return $this->RowConstructorNoKeyword();
    }

    protected function RowConstructorNoKeyword(): nodes\expressions\RowExpression
    {
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        $fields = $this->ExpressionListWithDefault();
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        return new nodes\expressions\RowExpression($fields);
    }

    /**
     * @return nodes\expressions\SubselectExpression|nodes\expressions\ArrayExpression
     */
    protected function ArrayConstructor(): nodes\ScalarExpression
    {
        $this->stream->expect(Token::TYPE_KEYWORD, 'array');
        if (!$this->stream->matchesSpecialChar(['[', '('])) {
            throw exceptions\SyntaxException::expectationFailed(
                Token::TYPE_SPECIAL_CHAR,
                ['[', '('],
                $this->stream->getCurrent(),
                $this->stream->getSource()
            );

        } elseif ('(' === $this->stream->getCurrent()->getValue()) {
            return new nodes\expressions\SubselectExpression($this->SelectWithParentheses(), 'array');

        } else {
            return $this->ArrayExpression();
        }
    }

    protected function ArrayExpression(): nodes\expressions\ArrayExpression
    {
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '[');
        if ($this->stream->matchesSpecialChar(']')) {
            $this->stream->next();
            return new nodes\expressions\ArrayExpression();
        }

        if (!$this->stream->matchesSpecialChar('[')) {
            $array = new nodes\expressions\ArrayExpression($this->ExpressionList());

        } else {
            $array = new nodes\expressions\ArrayExpression([$this->ArrayExpression()]);
            while ($this->stream->matchesSpecialChar(',')) {
                $this->stream->next();
                $array[] = $this->ArrayExpression();
            }
        }
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ']');

        return $array;
    }

    protected function CaseExpression(): nodes\expressions\CaseExpression
    {
        $argument    = null;
        $whenClauses = [];
        $elseClause  = null;

        $this->stream->expect(Token::TYPE_KEYWORD, 'case');
        // "simple" variant?
        if (!$this->stream->matchesKeyword('when')) {
            $argument = $this->Expression();
        }

        // requires at least one WHEN clause
        do {
            $this->stream->expect(Token::TYPE_KEYWORD, 'when');
            $when = $this->Expression();
            $this->stream->expect(Token::TYPE_KEYWORD, 'then');
            $then = $this->Expression();
            $whenClauses[] = new nodes\expressions\WhenExpression($when, $then);
        } while ($this->stream->matchesKeyword('when'));

        // may have an ELSE clause
        if ($this->stream->matchesKeyword('else')) {
            $this->stream->next();
            $elseClause = $this->Expression();
        }
        $this->stream->expect(Token::TYPE_KEYWORD, 'end');

        return new nodes\expressions\CaseExpression($whenClauses, $elseClause, $argument);
    }

    protected function GroupingExpression(): nodes\expressions\GroupingExpression
    {
        $this->stream->expect(Token::TYPE_KEYWORD, 'grouping');
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        $expression = new nodes\expressions\GroupingExpression($this->ExpressionList());
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        return $expression;
    }

    protected function ConstLeadingTypecast(): nodes\expressions\TypecastExpression
    {
        if (null !== ($typeCast = $this->IntervalLeadingTypecast())) {
            // interval is a special case since its options may come *after* string constant
            return $typeCast;
        }

        $typeName = $this->DateTimeTypeName()
                    ?? $this->CharacterTypeName(true)
                    ?? $this->BitTypeName(true)
                    ?? $this->NumericTypeName();

        if (null !== $typeName) {
            return new nodes\expressions\TypecastExpression(
                nodes\expressions\Constant::createFromToken($this->stream->expect(Token::TYPE_STRING)),
                $typeName
            );
        }

        throw new exceptions\SyntaxException('Expecting type name, got ' . $this->stream->getCurrent());
    }

    protected function GenericLeadingTypecast(array $identifiers): nodes\expressions\TypecastExpression
    {
        $modifiers = $this->GenericTypeModifierList();
        return new nodes\expressions\TypecastExpression(
            nodes\expressions\Constant::createFromToken($this->stream->expect(Token::TYPE_STRING)),
            new nodes\TypeName(new nodes\QualifiedName(...$identifiers), $modifiers)
        );
    }

    protected function SystemFunctionCallNoParens(): ?nodes\expressions\SQLValueFunction
    {
        if (!$this->stream->matchesKeyword(nodes\expressions\SQLValueFunction::NO_MODIFIERS)) {
            return null;
        }

        return new nodes\expressions\SQLValueFunction($this->stream->next()->getValue());
    }

    protected function SystemFunctionCallOptionalParens(): ?nodes\expressions\SQLValueFunction
    {
        if (!$this->stream->matchesKeyword(nodes\expressions\SQLValueFunction::OPTIONAL_MODIFIERS)) {
            return null;
        }

        $funcName = $this->stream->next()->getValue();
        $modifier = null;
        if ($this->stream->matchesSpecialChar('(')) {
            $this->stream->next();
            $modifier = new nodes\expressions\NumericConstant(
                $this->stream->expect(Token::TYPE_INTEGER)->getValue()
            );
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        }

        return new nodes\expressions\SQLValueFunction($funcName, $modifier);
    }

    protected function SystemFunctionCallRequiredParens(): ?nodes\FunctionLike
    {
        if (!$this->stream->matchesKeyword(self::SYSTEM_FUNCTIONS)) {
            return null;
        }
        $funcName  = $this->stream->next()->getValue();
        $arguments = [];
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');

        switch ($funcName) {
            case 'treat':
                // TREAT is "a bit" undocumented and buggy:
                // select treat('' as public.hstore); -> ERROR: function pg_catalog.hstore(unknown) does not exist
                // can be traced to revision 68d9fbeb5511d846ce3a6f66b8955d3ca55a4b76 from 2002
                throw new exceptions\NotImplementedException('TREAT() function support is not implemented');

            case 'cast':
                $value = $this->Expression();
                $this->stream->expect(Token::TYPE_KEYWORD, 'as');
                $funcNode = new nodes\expressions\TypecastExpression($value, $this->TypeName());
                break;

            case 'extract':
                $funcName = 'date_part';
                if (
                    $this->stream->matchesKeyword(['year', 'month', 'day', 'hour', 'minute', 'second'])
                    || $this->stream->matches(Token::TYPE_STRING)
                ) {
                    $arguments[] = new nodes\expressions\StringConstant($this->stream->next()->getValue());
                } else {
                    $arguments[] = new nodes\expressions\StringConstant(
                        $this->stream->expect(Token::TYPE_IDENTIFIER)->getValue()
                    );
                }
                $this->stream->expect(Token::TYPE_KEYWORD, 'from');
                $arguments[] = $this->Expression();
                break;

            case 'overlay':
                $arguments[] = $this->Expression();
                $this->stream->expect(Token::TYPE_KEYWORD, 'placing');
                $arguments[] = $this->Expression();
                $this->stream->expect(Token::TYPE_KEYWORD, 'from');
                $arguments[] = $this->Expression();
                if ($this->stream->matchesKeyword('for')) {
                    $this->stream->next();
                    $arguments[] = $this->Expression();
                }
                break;

            case 'position':
                // position(A in B) = "position"(B, A)
                $arguments[] = $this->RestrictedExpression();
                $this->stream->expect(Token::TYPE_KEYWORD, 'in');
                array_unshift($arguments, $this->RestrictedExpression());
                break;

            case 'substring':
                $arguments = $this->SubstringFunctionArguments();
                break;

            case 'trim':
                [$funcName, $arguments] = $this->TrimFunctionArguments();
                break;

            case 'nullif': // only two arguments, so don't use ExpressionList()
                $first    = $this->Expression();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
                $second   = $this->Expression();
                $funcNode = new nodes\expressions\NullIfExpression($first, $second);
                break;

            case 'xmlelement':
                $funcNode = $this->XmlElementFunction();
                break;

            case 'xmlexists':
                $arguments = $this->XmlExistsArguments();
                break;

            case 'xmlforest':
                $funcNode = new nodes\xml\XmlForest($this->XmlAttributeList());
                break;

            case 'xmlparse':
                $docOrContent = $this->stream->expect(Token::TYPE_KEYWORD, ['document', 'content'])->getValue();
                $value        = $this->Expression();
                $preserve     = false;
                if ($this->stream->matchesKeywordSequence('preserve', 'whitespace')) {
                    $preserve = true;
                    $this->stream->next();
                    $this->stream->next();
                } elseif ($this->stream->matchesKeywordSequence('strip', 'whitespace')) {
                    $this->stream->next();
                    $this->stream->next();
                }
                $funcNode = new nodes\xml\XmlParse($docOrContent, $value, $preserve);
                break;

            case 'xmlpi':
                $this->stream->expect(Token::TYPE_KEYWORD, 'name');
                if ($this->stream->matches(Token::TYPE_KEYWORD)) {
                    $name = nodes\Identifier::createFromToken($this->stream->next());
                } else {
                    $name = nodes\Identifier::createFromToken($this->stream->expect(Token::TYPE_IDENTIFIER));
                }
                $content = null;
                if ($this->stream->matchesSpecialChar(',')) {
                    $this->stream->next();
                    $content = $this->Expression();
                }

                $funcNode = new nodes\xml\XmlPi($name, $content);
                break;

            case 'xmlroot':
                $funcNode = $this->XmlRoot();
                break;

            case 'xmlserialize':
                $docOrContent = $this->stream->expect(Token::TYPE_KEYWORD, ['document', 'content'])->getValue();
                $value        = $this->Expression();
                $this->stream->expect(Token::TYPE_KEYWORD, 'as');
                $typeName     = $this->SimpleTypeName();
                $funcNode     = new nodes\xml\XmlSerialize($docOrContent, $value, $typeName);
                break;

            case 'normalize':
                $arguments[] = $this->Expression();
                if ($this->stream->matchesSpecialChar(',')) {
                    $this->stream->skip(1);
                    $form = $this->stream->expect(Token::TYPE_KEYWORD, ['nfc', 'nfd', 'nfkc', 'nfkd']);
                    $arguments[] = new nodes\expressions\StringConstant($form->getValue());
                }
                break;

            default: // 'coalesce', 'greatest', 'least', 'xmlconcat'
                $funcNode = new nodes\expressions\SystemFunctionCall(
                    $funcName,
                    $this->ExpressionList()
                );
        }

        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        if (empty($funcNode)) {
            $funcNode = new nodes\FunctionCall(
                new nodes\QualifiedName('pg_catalog', $funcName),
                new nodes\lists\FunctionArgumentList($arguments)
            );
        }
        return $funcNode;
    }

    /**
     * @return array{string, iterable<nodes\ScalarExpression>}
     */
    protected function TrimFunctionArguments(): array
    {
        if (!$this->stream->matchesKeyword(['both', 'leading', 'trailing'])) {
            $funcName = 'btrim';
        } else {
            switch ($this->stream->next()->getValue()) {
                case 'leading':
                    $funcName = 'ltrim';
                    break;
                case 'trailing':
                    $funcName = 'rtrim';
                    break;
                case 'both':
                default:
                    $funcName = 'btrim';
            }
        }

        if ($this->stream->matchesKeyword('from')) {
            $this->stream->next();
            $arguments = $this->ExpressionList();
        } else {
            $first = $this->Expression();
            if ($this->stream->matchesKeyword('from')) {
                $this->stream->next();
                $arguments   = $this->ExpressionList();
                $arguments[] = $first;
            } elseif ($this->stream->matchesSpecialChar(',')) {
                $this->stream->next();
                $arguments = new nodes\lists\ExpressionList([$first]);
                $arguments->merge($this->ExpressionList());
            } else {
                $arguments = [$first];
            }
        }

        return [$funcName, $arguments];
    }

    protected function SubstringFunctionArguments(): nodes\lists\FunctionArgumentList
    {
        $arguments = new nodes\lists\FunctionArgumentList([$this->Expression()]);
        $from  = $for = null;
        if (!$this->stream->matchesKeyword(['from', 'for'])) {
            if ($this->stream->matchesSpecialChar(',')) {
                $this->stream->next();
            } else {
                $token = $this->stream->getCurrent();
                throw exceptions\SyntaxException::atPosition(
                    "Unexpected {$token}, expecting ',' or 'from' or 'for'",
                    $this->stream->getSource(),
                    $token->getPosition()
                );
            }
            $arguments->merge($this->ExpressionList());

        } else {
            if ('from' === $this->stream->next()->getValue()) {
                $from = $this->Expression();
            } else {
                $for  = $this->Expression();
            }
            if ($this->stream->matchesKeyword(['from', 'for'])) {
                if (!$from && 'from' === $this->stream->getCurrent()->getValue()) {
                    $this->stream->next();
                    $from = $this->Expression();

                } elseif (!$for && 'for' === $this->stream->getCurrent()->getValue()) {
                    $this->stream->next();
                    $for  = $this->Expression();
                }
            }
        }

        if ($from && $for) {
            $arguments->merge([$from, $for]);
        } elseif (null !== $from) {
            $arguments[] = $from;
        } elseif (null !== $for) {
            $arguments->merge([new nodes\expressions\NumericConstant('1'), $for]);
        }

        return $arguments;
    }

    protected function XmlElementFunction(): nodes\xml\XmlElement
    {
        $this->stream->expect(Token::TYPE_KEYWORD, 'name');
        if ($this->stream->matches(Token::TYPE_KEYWORD)) {
            $name = nodes\Identifier::createFromToken($this->stream->next());
        } else {
            $name = nodes\Identifier::createFromToken($this->stream->expect(Token::TYPE_IDENTIFIER));
        }
        $attributes = $content = null;
        if ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            if (!$this->stream->matchesKeyword('xmlattributes')) {
                $content = $this->ExpressionList();
            } else {
                $this->stream->next();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
                $attributes = new nodes\lists\TargetList($this->XmlAttributeList());
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
                if ($this->stream->matchesSpecialChar(',')) {
                    $this->stream->next();
                    $content = $this->ExpressionList();
                }
            }
        }
        return new nodes\xml\XmlElement($name, $attributes, $content);
    }

    protected function XmlRoot(): nodes\xml\XmlRoot
    {
        $xml = $this->Expression();
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
        $this->stream->expect(Token::TYPE_KEYWORD, 'version');
        $version = $this->stream->matchesKeywordSequence('no', 'value') ? null : $this->Expression();
        if (!$this->stream->matchesSpecialChar(',')) {
            $standalone = null;
        } else {
            $this->stream->next();
            $this->stream->expect(Token::TYPE_KEYWORD, 'standalone');
            if ($this->stream->matchesKeywordSequence('no', 'value')) {
                $this->stream->next();
                $this->stream->next();
                $standalone = 'no value';
            } else {
                $standalone = $this->stream->expect(Token::TYPE_KEYWORD, ['yes', 'no'])->getValue();
            }
        }
        return new nodes\xml\XmlRoot($xml, $version, $standalone);
    }

    /**
     * @return nodes\TargetElement[]
     */
    protected function XmlAttributeList(): array
    {
        $attributes = [$this->XmlAttribute()];

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $attributes[] = $this->XmlAttribute();
        }

        return $attributes;
    }

    protected function XmlAttribute(): nodes\TargetElement
    {
        $value   = $this->Expression();
        $attName = null;
        if ($this->stream->matchesKeyword('as')) {
            $this->stream->next();
            if ($this->stream->matches(Token::TYPE_KEYWORD)) {
                $attName = nodes\Identifier::createFromToken($this->stream->next());
            } else {
                $attName = nodes\Identifier::createFromToken($this->stream->expect(Token::TYPE_IDENTIFIER));
            }
        }
        return new nodes\TargetElement($value, $attName);
    }

    protected function convertSpecialFunctionCallToFunctionExpression(
        nodes\FunctionLike $function
    ): nodes\ScalarExpression {
        if ($function instanceof nodes\FunctionCall) {
            return new nodes\expressions\FunctionExpression(
                clone $function->name,
                clone $function->arguments,
                $function->distinct,
                $function->variadic,
                clone $function->order
            );
        } elseif ($function instanceof nodes\ScalarExpression) {
            return $function;
        }

        throw new exceptions\InvalidArgumentException(
            __FUNCTION__ . "() requires an instance of FunctionCall or ScalarExpression, "
            . get_class($function) . " given"
        );
    }

    protected function FunctionExpression(array $identifiers): nodes\ScalarExpression
    {
        $function    = $this->GenericFunctionCall($identifiers);
        $withinGroup = false;
        $order       = $filter = $over = null;

        if ($this->stream->matchesKeywordSequence('within', 'group')) {
            if (count($function->order) > 0) {
                throw exceptions\SyntaxException::atPosition(
                    'Cannot use multiple ORDER BY clauses with WITHIN GROUP',
                    $this->stream->getSource(),
                    $this->stream->getCurrent()->getPosition()
                );
            }
            if ($function->distinct) {
                throw exceptions\SyntaxException::atPosition(
                    'Cannot use DISTINCT with WITHIN GROUP',
                    $this->stream->getSource(),
                    $this->stream->getCurrent()->getPosition()
                );
            }
            if ($function->variadic) {
                throw exceptions\SyntaxException::atPosition(
                    'Cannot use VARIADIC with WITHIN GROUP',
                    $this->stream->getSource(),
                    $this->stream->getCurrent()->getPosition()
                );
            }
            $withinGroup = true;
            $this->stream->skip(2);
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $this->stream->expect(Token::TYPE_KEYWORD, 'order');
            $this->stream->expect(Token::TYPE_KEYWORD, 'by');
            $order = $this->OrderByList();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        }
        if ($this->stream->matchesKeyword('filter')) {
            $this->stream->next();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $this->stream->expect(Token::TYPE_KEYWORD, 'where');
            $filter = $this->Expression();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        }
        if ($this->stream->matchesKeyword('over')) {
            $this->stream->next();
            $over = $this->WindowSpecification();
        }
        return new nodes\expressions\FunctionExpression(
            clone $function->name,
            clone $function->arguments,
            $function->distinct,
            $function->variadic,
            $order ?: clone $function->order,
            $withinGroup,
            $filter,
            $over
        );
    }

    protected function SpecialFunctionCall(): ?nodes\FunctionLike
    {
        $funcNode = $this->SystemFunctionCallNoParens()
                    ?? $this->SystemFunctionCallOptionalParens()
                    ?? $this->SystemFunctionCallRequiredParens();

        if (null === $funcNode && $this->stream->matchesKeywordSequence('collation', 'for')) {
            $this->stream->skip(2);
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $argument = $this->Expression();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            $funcNode = new nodes\FunctionCall(
                new nodes\QualifiedName('pg_catalog', 'pg_collation_for'),
                new nodes\lists\FunctionArgumentList([$argument])
            );
        }

        return $funcNode;
    }

    protected function GenericFunctionCall(array $identifiers = []): nodes\FunctionCall
    {
        $positionalArguments = $namedArguments = [];
        $variadic = $distinct = false;
        $orderBy  = null;

        $funcName = empty($identifiers) ? $this->GenericFunctionName() : new nodes\QualifiedName(...$identifiers);

        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        if ($this->stream->matchesSpecialChar('*')) {
            $this->stream->next();
            $positionalArguments = new nodes\Star();

        } elseif (!$this->stream->matchesSpecialChar(')')) {
            if ($this->stream->matchesKeyword(['distinct', 'all'])) {
                $distinct = 'distinct' === $this->stream->next()->getValue();
            }
            [$value, $name, $variadic] = $this->GenericFunctionArgument();
            if (!$name) {
                $positionalArguments[] = $value;
            } else {
                $namedArguments[(string)$name] = $value;
            }

            while (!$variadic && $this->stream->matchesSpecialChar(',')) {
                $this->stream->next();

                $argToken = $this->stream->getCurrent();
                [$value, $name, $variadic] = $this->GenericFunctionArgument();
                if (!$name) {
                    if (empty($namedArguments)) {
                        $positionalArguments[] = $value;
                    } else {
                        throw exceptions\SyntaxException::atPosition(
                            'Positional argument cannot follow named argument',
                            $this->stream->getSource(),
                            $argToken->getPosition()
                        );
                    }
                } elseif (!isset($namedArguments[(string)$name])) {
                    $namedArguments[(string)$name] = $value;
                } else {
                    throw exceptions\SyntaxException::atPosition(
                        "Argument name {$name} used more than once",
                        $this->stream->getSource(),
                        $argToken->getPosition()
                    );
                }
            }
            if ($this->stream->matchesKeywordSequence('order', 'by')) {
                $this->stream->skip(2);
                $orderBy = $this->OrderByList();
            }
        }
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        return new nodes\FunctionCall(
            $funcName,
            $positionalArguments instanceof nodes\Star
            ? $positionalArguments : new nodes\lists\FunctionArgumentList($positionalArguments + $namedArguments),
            $distinct,
            $variadic,
            $orderBy
        );
    }

    protected function GenericFunctionName(): nodes\QualifiedName
    {
        if (isset(self::ATOM_IDENTIFIER_TYPES[$this->stream->getCurrent()->getType()])) {
            $firstToken = $this->stream->next();
        } else {
            $firstToken = $this->stream->expect(Token::TYPE_IDENTIFIER);
        }
        $funcName = [nodes\Identifier::createFromToken($firstToken)];

        while ($this->stream->matchesSpecialChar('.')) {
            $this->stream->next();
            if ($this->stream->matches(Token::TYPE_KEYWORD)) {
                $funcName[] = nodes\Identifier::createFromToken($this->stream->next());
            } else {
                $funcName[] = nodes\Identifier::createFromToken($this->stream->expect(Token::TYPE_IDENTIFIER));
            }
        }

        if (
            Token::TYPE_TYPE_FUNC_NAME_KEYWORD === $firstToken->getType() && 1 < count($funcName)
            || Token::TYPE_COL_NAME_KEYWORD === $firstToken->getType() && 1 === count($funcName)
        ) {
            throw exceptions\SyntaxException::atPosition(
                implode('.', $funcName) . ' is not a valid function name',
                $this->stream->getSource(),
                $firstToken->getPosition()
            );
        }

        return new nodes\QualifiedName(...$funcName);
    }

    /**
     * Parses (maybe named or variadic) function argument
     *
     * @return array{nodes\ScalarExpression, ?nodes\Identifier, bool}
     */
    protected function GenericFunctionArgument(): array
    {
        if ($variadic = $this->stream->matchesKeyword('variadic')) {
            $this->stream->next();
        }

        $name = null;
        // it's the only place this shit can appear in
        if (
            $this->stream->look(1)->matches(Token::TYPE_COLON_EQUALS)
            || $this->stream->look(1)->matches(Token::TYPE_EQUALS_GREATER)
        ) {
            if ($this->stream->matchesAnyType(Token::TYPE_UNRESERVED_KEYWORD, Token::TYPE_TYPE_FUNC_NAME_KEYWORD)) {
                $name = nodes\Identifier::createFromToken($this->stream->next());
            } else {
                $name = nodes\Identifier::createFromToken($this->stream->expect(Token::TYPE_IDENTIFIER));
            }
            $this->stream->next();
        }

        return [$this->Expression(), $name, $variadic];
    }

    /**
     * @param nodes\Identifier[] $identifiers Name parts matched in GenericExpressionAtom()
     * @return nodes\ColumnReference|nodes\Indirection
     */
    protected function ColumnReference(array $identifiers): nodes\ScalarExpression
    {
        $parts       = [array_shift($identifiers)];
        $indirection = array_merge($identifiers, $this->Indirection());
        while (!empty($indirection) && !($indirection[0] instanceof nodes\ArrayIndexes)) {
            $parts[] = array_shift($indirection);
        }
        /** @var array<nodes\Identifier|nodes\Star> $parts */
        if (!empty($indirection)) {
            return new nodes\Indirection($indirection, new nodes\ColumnReference(...$parts));
        }

        return new nodes\ColumnReference(...$parts);
    }

    /**
     * @param bool $allowStar Whether to allow Star nodes in returned array
     * @return array<nodes\Identifier|nodes\ArrayIndexes|nodes\Star>
     */
    protected function Indirection(bool $allowStar = true): array
    {
        $indirection = [];
        while ($this->stream->matchesSpecialChar(['[', '.'])) {
            if ('.' === $this->stream->next()->getValue()) {
                if ($this->stream->matchesSpecialChar('*')) {
                    if (!$allowStar) {
                        // this will basically trigger an error if '.*' appears in list of fields for INSERT or UPDATE
                        $this->stream->expect(Token::TYPE_IDENTIFIER);
                    }
                    $this->stream->next();
                    $indirection[] = new nodes\Star();
                    break;
                } elseif ($this->stream->matches(Token::TYPE_KEYWORD)) {
                    $indirection[] = nodes\Identifier::createFromToken($this->stream->next());
                } else {
                    $indirection[] = nodes\Identifier::createFromToken($this->stream->expect(Token::TYPE_IDENTIFIER));
                }

            } else {
                $lower = $upper = null;
                $isSlice = false;

                if (!$this->stream->matchesSpecialChar(':')) {
                    $lower = $this->Expression();
                }
                if ($this->stream->matchesSpecialChar(':')) {
                    $this->stream->next();
                    $isSlice = true;
                    if (!$this->stream->matchesSpecialChar(']')) {
                        $upper = $this->Expression();
                    }
                }
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ']');

                $indirection[] = $isSlice
                                 ? new nodes\ArrayIndexes($lower, $upper, true)
                                 : new nodes\ArrayIndexes(null, $lower);
            }
        }
        return $indirection;
    }

    protected function TargetList(): nodes\lists\TargetList
    {
        $elements = new nodes\lists\TargetList([$this->TargetElement()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $elements[] = $this->TargetElement();
        }

        return $elements;
    }

    /**
     * @return nodes\Star|nodes\TargetElement
     */
    protected function TargetElement(): Node
    {
        $alias = null;

        if ($this->stream->matchesSpecialChar('*')) {
            $this->stream->next();
            return new nodes\Star();
        }
        $element = $this->Expression();
        if (
            $this->stream->matches(Token::TYPE_IDENTIFIER)
            || $this->stream->matches(Token::TYPE_KEYWORD)
               && Keywords::isBareLabelKeyword($this->stream->getCurrent()->getValue())
        ) {
            $alias = nodes\Identifier::createFromToken($this->stream->next());

        } elseif ($this->stream->matchesKeyword('as')) {
            $this->stream->next();
            if ($this->stream->matches(Token::TYPE_KEYWORD)) {
                $alias = nodes\Identifier::createFromToken($this->stream->next());
            } else {
                $alias = nodes\Identifier::createFromToken($this->stream->expect(Token::TYPE_IDENTIFIER));
            }
        }

        return new nodes\TargetElement($element, $alias);
    }

    protected function FromList(): nodes\lists\FromList
    {
        $relations = new nodes\lists\FromList([$this->FromElement()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $relations[] = $this->FromElement();
        }

        return $relations;
    }

    protected function FromElement(): nodes\range\FromElement
    {
        $left = $this->TableReference();

        while (
            $this->stream->matchesKeyword(['cross', 'natural', 'left', 'right', 'full', 'inner', 'join'])
        ) {
            // CROSS JOIN needs no join quals
            if ('cross' === $this->stream->getCurrent()->getValue()) {
                $this->stream->next();
                $this->stream->expect(Token::TYPE_KEYWORD, 'join');
                $left = new nodes\range\JoinExpression($left, $this->TableReference(), 'cross');
                continue;
            }
            if ('natural' === $this->stream->getCurrent()->getValue()) {
                $this->stream->next();
                $natural = true;
            } else {
                $natural = false;
            }

            if ($this->stream->matchesKeyword('join')) {
                $this->stream->next();
                $joinType = 'inner';
            } else {
                $joinType = $this->stream->expect(Token::TYPE_KEYWORD, ['left', 'right', 'full', 'inner'])
                                ->getValue();
                // noise word
                if ($this->stream->matchesKeyword('outer')) {
                    $this->stream->next();
                }
                $this->stream->expect(Token::TYPE_KEYWORD, 'join');
            }
            $left = new nodes\range\JoinExpression($left, $this->TableReference(), $joinType);

            if ($natural) {
                $left->setNatural(true);

            } else {
                $token = $this->stream->expect(Token::TYPE_KEYWORD, ['on', 'using']);
                if ('on' === $token->getValue()) {
                    $left->setOn($this->Expression());
                } else {
                    $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
                    $using = $this->ColIdList();
                    $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
                    $left->setUsing($using);
                }
            }
        }

        return $left;
    }

    protected function TableReference(): nodes\range\FromElement
    {
        if ($this->stream->matchesKeyword('lateral')) {
            $this->stream->next();
            // lateral can only apply to subselects, XMLTABLEs or function invocations
            if ($this->stream->matchesSpecialChar('(')) {
                $reference = $this->RangeSubselect();
            } elseif ($this->stream->matchesKeyword('xmltable')) {
                $reference = $this->XmlTable();
            } else {
                $reference = $this->RangeFunctionCall();
            }
            $reference->setLateral(true);

        } elseif ($this->stream->matchesSpecialChar('(')) {
            // parentheses may contain either a subselect or JOIN expression
            if (self::PARENTHESES_SELECT === $this->checkContentsOfParentheses()) {
                $reference = $this->RangeSubselect();
            } else {
                $this->stream->next();
                $reference = $this->FromElement();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
                if ($alias = $this->OptionalAliasClause()) {
                    $reference->setAlias($alias[0], $alias[1]);
                }
            }

        } elseif ($this->stream->matchesKeyword('xmltable')) {
            $reference = $this->XmlTable();

        } elseif (
            $this->stream->matchesKeywordSequence('rows', 'from')
                  || $this->matchesFunctionCall()
        ) {
            $reference = $this->RangeFunctionCall();

        } else {
            $reference = $this->RelationExpression();
        }

        return $reference;
    }

    protected function RangeSubselect(): nodes\range\Subselect
    {
        $token     = $this->stream->getCurrent();
        $reference = new nodes\range\Subselect($this->SelectWithParentheses());

        if (!($alias = $this->OptionalAliasClause())) {
            throw exceptions\SyntaxException::atPosition(
                'Subselects in FROM clause should have an alias',
                $this->stream->getSource(),
                $token->getPosition()
            );
        }
        $reference->setAlias($alias[0], $alias[1]);

        return $reference;
    }

    protected function RangeFunctionCall(): nodes\range\FunctionFromElement
    {
        if (!$this->stream->matchesKeywordSequence('rows', 'from')) {
            $reference = new nodes\range\FunctionCall($this->SpecialFunctionCall() ?? $this->GenericFunctionCall());
        } else {
            $this->stream->skip(2);
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $list = new nodes\lists\RowsFromList([$this->RowsFromElement()]);
            while ($this->stream->matchesSpecialChar(',')) {
                $this->stream->next();
                $list[] = $this->RowsFromElement();
            }
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

            $reference = new nodes\range\RowsFrom($list);
        }

        if ($this->stream->matchesKeywordSequence('with', 'ordinality')) {
            $this->stream->skip(2);
            $reference->setWithOrdinality(true);
        }

        if ($alias = $this->OptionalAliasClause(true)) {
            $reference->setAlias($alias[0], $alias[1]);
        }

        return $reference;
    }

    protected function RowsFromElement(): nodes\range\RowsFromElement
    {
        $function = $this->SpecialFunctionCall() ?? $this->GenericFunctionCall();

        if (!$this->stream->matchesKeyword('as')) {
            $aliases = null;
        } else {
            $this->stream->next();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $aliases = new nodes\lists\ColumnDefinitionList([$this->TableFuncElement()]);
            while ($this->stream->matchesSpecialChar(',')) {
                $this->stream->next();
                $aliases[] = $this->TableFuncElement();
            }
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        }
        return new nodes\range\RowsFromElement($function, $aliases);
    }

    /**
     * relation_expr_opt_alias from grammar, with no special case for SET
     *
     * Not used in parser itself, needed by StatementFactory
     *
     * @return nodes\range\UpdateOrDeleteTarget
     */
    protected function RelationExpressionOptAlias(): nodes\range\UpdateOrDeleteTarget
    {
        return $this->UpdateOrDeleteTarget(self::RELATION_FORMAT_DELETE);
    }

    protected function InsertTarget(): nodes\range\InsertTarget
    {
        $name  = $this->QualifiedName();
        $alias = null;

        // AS is required for aliases in INSERT
        if ($this->stream->matchesKeyword('as')) {
            $this->stream->next();
            $alias = $this->ColId();
        }

        return new nodes\range\InsertTarget($name, $alias);
    }

    /**
     * @return nodes\range\RelationReference|nodes\range\TableSample
     */
    protected function RelationExpression(): nodes\range\FromElement
    {
        $expression = new nodes\range\RelationReference(...$this->QualifiedNameWithInheritOption());

        if ($alias = $this->OptionalAliasClause()) {
            $expression->setAlias($alias[0], $alias[1]);
        }

        if ($this->stream->matchesKeyword('tablesample')) {
            $this->stream->next();
            $method     = $this->GenericFunctionName();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $arguments  = $this->ExpressionList();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

            $repeatable = null;
            if ($this->stream->matchesKeyword('repeatable')) {
                $this->stream->next();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
                $repeatable = $this->Expression();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }

            $expression = new nodes\range\TableSample($expression, $method, $arguments, $repeatable);
        }

        return $expression;
    }

    protected function UpdateOrDeleteTarget(
        string $statementType = self::RELATION_FORMAT_UPDATE
    ): nodes\range\UpdateOrDeleteTarget {
        [$name, $inherit] = $this->QualifiedNameWithInheritOption();
        return new nodes\range\UpdateOrDeleteTarget(
            $name,
            $this->DMLAliasClause($statementType),
            $inherit
        );
    }

    /**
     * Common part of relation reference for SELECT / UPDATE / DELETE statements
     *
     * @return array{nodes\QualifiedName, bool|null}
     */
    protected function QualifiedNameWithInheritOption(): array
    {
        $inherit           = null;
        $expectParenthesis = false;
        if ($this->stream->matchesKeyword('only')) {
            $this->stream->next();
            $inherit = false;
            if ($this->stream->matchesSpecialChar('(')) {
                $expectParenthesis = true;
                $this->stream->next();
            }
        }

        $name = $this->QualifiedName();

        if (false === $inherit && $expectParenthesis) {
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        } elseif (null === $inherit && $this->stream->matchesSpecialChar('*')) {
            $this->stream->next();
            $inherit = true;
        }

        return [$name, $inherit];
    }

    /**
     *
     * Corresponds to relation_expr_opt_alias production from grammar, see the
     * comment there.
     *
     * @param string $statementType
     * @return nodes\Identifier|null
     */
    protected function DMLAliasClause(string $statementType): ?nodes\Identifier
    {
        if (
            $this->stream->matchesKeyword('as')
            || $this->stream->matchesAnyType(Token::TYPE_IDENTIFIER, Token::TYPE_COL_NAME_KEYWORD)
            || ($this->stream->matches(Token::TYPE_UNRESERVED_KEYWORD)
                && (self::RELATION_FORMAT_UPDATE !== $statementType
                    || 'set' !== $this->stream->getCurrent()->getValue()))
        ) {
            if ($this->stream->matchesKeyword('as')) {
                $this->stream->next();
            }
            return $this->ColId();
        }
        return null;
    }

    protected function OptionalAliasClause(bool $allowFunctionAlias = false): ?array
    {
        if (
            $this->stream->matchesKeyword('as')
            || $this->stream->matchesAnyType(
                Token::TYPE_IDENTIFIER,
                Token::TYPE_UNRESERVED_KEYWORD,
                Token::TYPE_COL_NAME_KEYWORD
            )
        ) {
            $tableAlias    = null;
            $columnAliases = null;

            // AS is complete noise here, unlike in TargetList
            if ($this->stream->matchesKeyword('as')) {
                $this->stream->next();
            }
            if (!$allowFunctionAlias || !$this->stream->matchesSpecialChar('(')) {
                $tableAlias = $this->ColId();
            }
            if (!$tableAlias || $this->stream->matchesSpecialChar('(')) {
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');

                if (
                    $allowFunctionAlias
                    // for TableFuncElement the next position will contain typename
                    && (!$tableAlias || !$this->stream->look()->matches(Token::TYPE_SPECIAL_CHAR, [')', ',']))
                ) {
                    $columnAliases = new nodes\lists\ColumnDefinitionList([$this->TableFuncElement()]);
                    while ($this->stream->matchesSpecialChar(',')) {
                        $this->stream->next();
                        $columnAliases[] = $this->TableFuncElement();
                    }
                } else {
                    $columnAliases = new nodes\lists\IdentifierList([$this->ColId()]);
                    while ($this->stream->matchesSpecialChar(',')) {
                        $this->stream->next();
                        $columnAliases[] = $this->ColId();
                    }
                }

                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }

            return [$tableAlias, $columnAliases];
        }
        return null;
    }

    protected function TableFuncElement(): nodes\range\ColumnDefinition
    {
        $alias     = $this->ColId();
        $type      = $this->TypeName();
        $collation = null;
        if ($this->stream->matchesKeyword('collate')) {
            $this->stream->next();
            $collation = $this->QualifiedName();
        }

        return new nodes\range\ColumnDefinition($alias, $type, $collation);
    }

    protected function ColIdList(): nodes\lists\IdentifierList
    {
        $list = new nodes\lists\IdentifierList([$this->ColId()]);
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $list[] = $this->ColId();
        }
        return $list;
    }

    /**
     * ColId production from Postgres grammar
     */
    protected function ColId(): nodes\Identifier
    {
        if ($this->stream->matchesAnyType(Token::TYPE_UNRESERVED_KEYWORD, Token::TYPE_COL_NAME_KEYWORD)) {
            return nodes\Identifier::createFromToken($this->stream->next());
        } else {
            return nodes\Identifier::createFromToken($this->stream->expect(Token::TYPE_IDENTIFIER));
        }
    }

    protected function QualifiedName(): nodes\QualifiedName
    {
        $parts = [$this->ColId()];

        while ($this->stream->matchesSpecialChar('.')) {
            $this->stream->next();
            if ($this->stream->matches(Token::TYPE_KEYWORD)) {
                $parts[] = nodes\Identifier::createFromToken($this->stream->next());
            } else {
                $parts[] = nodes\Identifier::createFromToken($this->stream->expect(Token::TYPE_IDENTIFIER));
            }
        }

        return new nodes\QualifiedName(...$parts);
    }

    protected function OrderByList(): nodes\lists\OrderByList
    {
        $items = new nodes\lists\OrderByList([$this->OrderByElement()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $items[] = $this->OrderByElement();
        }

        return $items;
    }

    protected function OrderByElement(): nodes\OrderByElement
    {
        $expression = $this->Expression();
        $operator   = $direction = $nullsOrder = null;
        if ($this->stream->matchesKeyword(['asc', 'desc', 'using'])) {
            if ('using' === ($direction = $this->stream->next()->getValue())) {
                $operator = $this->Operator(true);
            }
        }
        if ($this->stream->matchesKeyword('nulls')) {
            $this->stream->next();
            $nullsOrder = $this->stream->expect(Token::TYPE_KEYWORD, ['first', 'last'])->getValue();
        }

        return new nodes\OrderByElement($expression, $direction, $nullsOrder, $operator);
    }

    protected function OnConflict(): nodes\OnConflictClause
    {
        $target = $set = $condition = null;
        if ($this->stream->matchesKeywordSequence('on', 'constraint')) {
            $this->stream->skip(2);
            $target = $this->ColId();

        } elseif ($this->stream->matchesSpecialChar('(')) {
            $target = $this->IndexParameters();
        }

        $this->stream->expect(Token::TYPE_KEYWORD, 'do');
        if ('update' === ($action = $this->stream->expect(Token::TYPE_KEYWORD, ['update', 'nothing'])->getValue())) {
            $this->stream->expect(Token::TYPE_KEYWORD, 'set');
            $set = $this->SetClauseList();
            if ($this->stream->matchesKeyword('where')) {
                $this->stream->next();
                $condition = $this->Expression();
            }
        }

        return new nodes\OnConflictClause($action, $target, $set, $condition);
    }

    protected function IndexParameters(): nodes\IndexParameters
    {
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');

        $items = new nodes\IndexParameters([$this->IndexElement()]);
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $items[] = $this->IndexElement();
        }

        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        if ($this->stream->matchesKeyword('where')) {
            $this->stream->next();
            $items->where->condition = $this->Expression();
        }

        return $items;
    }


    protected function IndexElement(): nodes\IndexElement
    {
        if ($this->stream->matchesSpecialChar('(')) {
            $this->stream->next();
            $expression = $this->Expression();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        } elseif ($this->matchesFunctionCall()) {
            /** @var nodes\FunctionCall $function */
            $function = $this->SpecialFunctionCall() ?? $this->GenericFunctionCall();
            $expression = new nodes\expressions\FunctionExpression(
                clone $function->name,
                clone $function->arguments,
                $function->distinct,
                $function->variadic,
                clone $function->order
            );

        } else {
            $expression = $this->ColId();
        }

        $collation = $opClass = $direction = $nullsOrder = null;

        if ($this->stream->matchesKeyword('collate')) {
            $this->stream->next();
            $collation = $this->QualifiedName();
        }

        if (
            $this->stream->matchesAnyType(
                Token::TYPE_IDENTIFIER,
                Token::TYPE_UNRESERVED_KEYWORD,
                Token::TYPE_COL_NAME_KEYWORD
            )
        ) {
            $opClass = $this->QualifiedName();
        }

        if ($this->stream->matchesKeyword(['asc', 'desc'])) {
            $direction = $this->stream->next()->getValue();
        }

        if ($this->stream->matchesKeyword('nulls')) {
            $this->stream->next();
            $nullsOrder = $this->stream->expect(Token::TYPE_KEYWORD, ['first', 'last'])->getValue();
        }

        return new nodes\IndexElement($expression, $collation, $opClass, $direction, $nullsOrder);
    }

    protected function GroupByClause(): nodes\group\GroupByClause
    {
        $distinct = $this->stream->matchesKeyword(['all', 'distinct'])
                    && 'distinct' === $this->stream->next()->getValue();
        $this->GroupByListElements($clause = new nodes\group\GroupByClause(null, $distinct));
        return $clause;
    }

    protected function GroupByListElements(nodes\lists\GroupByList $list): void
    {
        $list[] = $this->GroupByElement();

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $list[] = $this->GroupByElement();
        }
    }

    /**
     * @return nodes\group\GroupByElement|nodes\ScalarExpression
     */
    protected function GroupByElement(): Node
    {
        if (
            $this->stream->matchesSpecialChar('(')
            && $this->stream->look()->matches(Token::TYPE_SPECIAL_CHAR, ')')
        ) {
            $this->stream->skip(2);
            $element = new nodes\group\EmptyGroupingSet();

        } elseif ($this->stream->matchesKeyword(['cube', 'rollup'])) {
            $type = $this->stream->next()->getValue();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $element = new nodes\group\CubeOrRollupClause($this->ExpressionList(), $type);
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        } elseif ($this->stream->matchesKeywordSequence('grouping', 'sets')) {
            $this->stream->skip(2);
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $this->GroupByListElements($element = new nodes\group\GroupingSetsClause());
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        } else {
            $element = $this->Expression();
        }

        return $element;
    }

    /**
     * @return nodes\ScalarExpression[]
     */
    protected function XmlExistsArguments(): array
    {
        $arguments = [$this->ExpressionAtom()];
        // 'by ref' is noise in Postgres
        $this->stream->expect(Token::TYPE_KEYWORD, 'passing');
        if ($this->stream->matchesKeyword('by')) {
            $this->stream->next();
            $this->stream->expect(Token::TYPE_KEYWORD, ['ref', 'value']);
        }
        $arguments[] = $this->ExpressionAtom();
        if ($this->stream->matchesKeyword('by')) {
            $this->stream->next();
            $this->stream->expect(Token::TYPE_KEYWORD, ['ref', 'value']);
        }

        return $arguments;
    }

    protected function XmlTable(): nodes\range\XmlTable
    {
        $this->stream->expect(Token::TYPE_KEYWORD, 'xmltable');
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');

        $namespaces = null;
        if ($this->stream->matchesKeyword('xmlnamespaces')) {
            $this->stream->next();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $namespaces = $this->XmlNamespaceList();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
        }
        $doc = $this->XmlExistsArguments();
        $this->stream->expect(Token::TYPE_KEYWORD, 'columns');
        $columns = $this->XmlColumnList();

        $table = new nodes\range\XmlTable($doc[0], $doc[1], $columns, $namespaces);

        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        if ($alias = $this->OptionalAliasClause()) {
            $table->setAlias($alias[0], $alias[1]);
        }

        return $table;
    }

    protected function XmlNamespaceList(): nodes\xml\XmlNamespaceList
    {
        $items = new nodes\xml\XmlNamespaceList([$this->XmlNamespace()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $items[] = $this->XmlNamespace();
        }

        return $items;
    }

    protected function XmlNamespace(): nodes\xml\XmlNamespace
    {
        // Default namespace is not currently supported, but Postgres accepts the syntax. We do the same.
        if ($this->stream->matchesKeyword('default')) {
            $this->stream->next();
            $value = $this->RestrictedExpression();
            $alias = null;

        } else {
            $value = $this->RestrictedExpression();
            $this->stream->expect(Token::TYPE_KEYWORD, 'as');
            if ($this->stream->matches(Token::TYPE_KEYWORD)) {
                $alias = nodes\Identifier::createFromToken($this->stream->next());
            } else {
                $alias = nodes\Identifier::createFromToken($this->stream->expect(Token::TYPE_IDENTIFIER));
            }
        }

        return new nodes\xml\XmlNamespace($value, $alias);
    }

    protected function XmlColumnList(): nodes\xml\XmlColumnList
    {
        $columns = new nodes\xml\XmlColumnList([$this->XmlColumnDefinition()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $columns[] = $this->XmlColumnDefinition();
        }

        return $columns;
    }

    protected function XmlColumnDefinition(): nodes\xml\XmlColumnDefinition
    {
        $name = $this->ColId();

        if ($this->stream->matchesKeywordSequence('for', 'ordinality')) {
            $this->stream->skip(2);
            return new nodes\xml\XmlOrdinalityColumnDefinition($name);
        }

        $type = $this->TypeName();
        $nullable = $default = $path = null;
        // 'path' is for some reason not a keyword in Postgres, so production for xmltable_column_option_el
        // accepts any identifier and then xmltable_column_el basically rejects anything that is not 'path'.
        // We explicitly check for 'path' here instead.
        do {
            if ($this->stream->matches(Token::TYPE_IDENTIFIER, 'path')) {
                if (null !== $path) {
                    throw exceptions\SyntaxException::atPosition(
                        "only one PATH value per column is allowed",
                        $this->stream->getSource(),
                        $this->stream->getCurrent()->getPosition()
                    );
                }
                $this->stream->next();
                $path = $this->RestrictedExpression();

            } elseif ($this->stream->matchesKeyword('default')) {
                if (null !== $default) {
                    throw exceptions\SyntaxException::atPosition(
                        "only one DEFAULT value is allowed",
                        $this->stream->getSource(),
                        $this->stream->getCurrent()->getPosition()
                    );
                }
                $this->stream->next();
                $default = $this->RestrictedExpression();

            } elseif ($this->stream->matchesKeyword('null')) {
                if (null !== $nullable) {
                    throw exceptions\SyntaxException::atPosition(
                        "conflicting or redundant NULL / NOT NULL declarations",
                        $this->stream->getSource(),
                        $this->stream->getCurrent()->getPosition()
                    );
                }
                $this->stream->next();
                $nullable = true;

            } elseif (
                $this->stream->matchesKeyword('not')
                      && $this->stream->look(1)->matches(Token::TYPE_KEYWORD, 'null')
            ) {
                if (null !== $nullable) {
                    throw exceptions\SyntaxException::atPosition(
                        "conflicting or redundant NULL / NOT NULL declarations",
                        $this->stream->getSource(),
                        $this->stream->getCurrent()->getPosition()
                    );
                }
                $this->stream->skip(2);
                $nullable = false;

            } else {
                break;
            }

        } while (true);

        return new nodes\xml\XmlTypedColumnDefinition($name, $type, $path, $nullable, $default);
    }
}
