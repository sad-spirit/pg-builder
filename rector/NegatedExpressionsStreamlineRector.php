<?php

/**
 * This class is a custom rule for rector tool (https://getrector.org/), see Upgrading.md
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\rector;

use PhpParser\Node;
use PhpParser\Node\{
    Arg,
    Expr\ConstFetch,
    Expr\New_,
    Name,
    Scalar\String_
};
use Rector\Core\{
    Rector\AbstractRector,
    RectorDefinition\CodeSample,
    RectorDefinition\RectorDefinition
};

/**
 * Expressions that allow negation (a NOT IN b, c NOT LIKE d) now expose it through $not property set via constructor
 */
final class NegatedExpressionsStreamlineRector extends AbstractRector
{
    /**
     * @inheritDoc
     */
    public function getNodeTypes(): array
    {
        return [New_::class];
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Changes constructors of negated BETWEEN / IN / IS OF / LIKE expressions',
            [new CodeSample(
                <<<'CODE_SAMPLE'
use sad_spirit\pg_builder\nodes\expressions\BetweenExpression;
use sad_spirit\pg_builder\nodes\expressions\PatternMatchingExpression;
use sad_spirit\pg_builder\nodes\expressions\InExpression;

$notBetween = new BetweenExpression($arg, $left, $right, 'not between symmetric');
$notLike    = new PatternMatchingExpression($arg, $pattern, 'not like');
$in         = new InExpression($arg, $subquery, 'in');
$notIn      = new InExpression($arg, $subquery, 'not in');
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
use sad_spirit\pg_builder\nodes\expressions\BetweenExpression;
use sad_spirit\pg_builder\nodes\expressions\PatternMatchingExpression;
use sad_spirit\pg_builder\nodes\expressions\InExpression;

$notBetween = new BetweenExpression($arg, $left, $right, 'between symmetric', true);
$notLike    = new PatternMatchingExpression($arg, $pattern, 'like', true);
$in         = new InExpression($arg, $subquery);
$notIn      = new InExpression($arg, $subquery, true);
CODE_SAMPLE
            )]
        );
    }

    /**
     * @inheritDoc
     * @param New_ $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($this->isObjectType($node->class, 'sad_spirit\pg_builder\nodes\expressions\BetweenExpression')) {
            return $this->refactorBetweenExpression($node);
        } elseif ($this->isObjectType($node->class, 'sad_spirit\pg_builder\nodes\expressions\InExpression')) {
            return $this->refactorInExpression($node);
        } elseif ($this->isObjectType($node->class, 'sad_spirit\pg_builder\nodes\expressions\IsOfExpression')) {
            return $this->refactorIsOfExpression($node);
        } elseif ($this->isObjectType($node->class, 'sad_spirit\pg_builder\nodes\expressions\PatternMatchingExpression')) {
            return $this->refactorPatternMatchingExpression($node);
        } else {
            return null;
        }
    }

    private function refactorBetweenExpression(New_ $node): ?New_
    {
        if (4 > count($node->args)) {
            return null;
        }
        if (
            !$node->args[3]->value instanceof String_
            || 'not ' !== substr($node->args[3]->value->value, 0, 4)
        ) {
            return null;
        }

        $node->args[3]->value = new String_(substr($node->args[3]->value->value, 4));
        $node->args[4] = new Arg(new ConstFetch(new Name('true')));

        return $node;
    }

    private function refactorInExpression(New_ $node): ?New_
    {
        if (
            3 > count($node->args)
            || !$node->args[2]->value instanceof String_
        ) {
            return null;
        }

        if ('in' === $node->args[2]->value->value) {
            array_splice($node->args, 2, 1, []);
        } else {
            $node->args[2] = new Arg(new ConstFetch(new Name('true')));
        }

        return $node;
    }

    private function refactorIsOfExpression(New_ $node): ?New_
    {
        if (
            3 > count($node->args)
            || !$node->args[2]->value instanceof String_
        ) {
            return null;
        }

        if ('is of' === $node->args[2]->value->value) {
            array_splice($node->args, 2, 1, []);
        } else {
            $node->args[2] = new Arg(new ConstFetch(new Name('true')));
        }

        return $node;
    }

    private function refactorPatternMatchingExpression(New_ $node): ?New_
    {
        if (
            3 > count($node->args) || 4 < count($node->args)
            || !$node->args[2]->value instanceof String_
        ) {
            return null;
        }
        $hasNot    = ('not ' === substr($node->args[2]->value->value, 0, 4));
        $hasEscape = !empty($node->args[3]);

        if (!$hasNot && !$hasEscape) {
            return null;
        } elseif (!$hasNot) {
            array_splice($node->args, 3, 0, [new Arg(new ConstFetch(new Name('false')))]);
        } else {
            $node->args[2]->value = new String_(substr($node->args[2]->value->value, 4));
            if (!$hasEscape) {
                $node->args[] = new Arg(new ConstFetch(new Name('true')));
            } else {
                array_splice($node->args, 3, 0, [new Arg(new ConstFetch(new Name('true')))]);
            }
        }

        return $node;
    }
}