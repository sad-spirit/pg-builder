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
 * Replaces usages of OperatorExpression by more specialized Nodes
 * @link https://github.com/sad-spirit/pg-builder/issues/8
 */
final class UseSpecializedNodesInsteadOfOperatorExpressionRector extends AbstractRector
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
            'Replaces OperatorExpression\'s used with something other than operators to proper Nodes',
            [new CodeSample(
                <<<'CODE_SAMPLE'
use sad_spirit\pg_builder\nodes\expressions\OperatorExpression;

$not          = new OperatorExpression('not', null, $expression);
$distinctFrom = new OperatorExpression('is not distinct from', $argumentOne, $argumentTwo);
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
use sad_spirit\pg_builder\nodes\expressions\OperatorExpression;
use sad_spirit\pg_builder\nodes\expressions\NotExpression;
use sad_spirit\pg_builder\nodes\expressions\IsDistinctFromExpression;

$not          = new NotExpression($expression);
$distinctFrom = new IsDistinctFromExpression($argumentOne, $argumentTwo, true);
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
        if (
            !$this->isObjectType($node->class, 'sad_spirit\pg_builder\nodes\expressions\OperatorExpression')
            || !$node->args[0]->value instanceof String_
        ) {
            return null;
        }

        switch ($node->args[0]->value->value) {
            case 'not':
                return $this->convertToNotExpression($node);
            case 'overlaps':
                return $this->convertToOverlapsExpression($node);
            case 'at time zone':
                return $this->convertToAtTimeZoneExpression($node);
            case 'is distinct from':
            case 'is not distinct from':
                return $this->convertToIsDistinctFromExpression($node);
            default:
                if ('is ' === substr($node->args[0]->value->value, 0, 3)) {
                    return $this->convertToIsExpression($node);
                }
                return null;
        }
    }

    private function convertToIsExpression(New_ $node): New_
    {
        preg_match('{^is (not )?(.+)$}', $node->args[0]->value->value, $m);
        array_shift($node->args);
        $node->args[1] = new Arg(new String_($m[2]));
        if (!empty($m[1])) {
            $node->args[2] = new Arg(new ConstFetch(new Name('true')));
        }
        $node->class = new Name('\sad_spirit\pg_builder\nodes\expressions\IsExpression');

        return $node;
    }

    private function convertToIsDistinctFromExpression(New_ $node): New_
    {
        if (false !== strpos($node->args[0]->value->value, 'not')) {
            $node->args[] = new Arg(new ConstFetch(new Name('true')));
        }
        array_shift($node->args);
        $node->class = new Name('\sad_spirit\pg_builder\nodes\expressions\IsDistinctFromExpression');

        return $node;
    }

    private function convertToNotExpression(New_ $node): New_
    {
        array_shift($node->args);
        array_shift($node->args);
        $node->class = new Name('\sad_spirit\pg_builder\nodes\expressions\NotExpression');

        return $node;
    }

    private function convertToOverlapsExpression(New_ $node): New_
    {
        array_shift($node->args);
        $node->class = new Name('\sad_spirit\pg_builder\nodes\expressions\OverlapsExpression');

        return $node;
    }

    private function convertToAtTimeZoneExpression(New_ $node): New_
    {
        array_shift($node->args);
        $node->class = new Name('\sad_spirit\pg_builder\nodes\expressions\AtTimeZoneExpression');

        return $node;
    }
}
