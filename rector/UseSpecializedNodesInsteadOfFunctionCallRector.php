<?php

/**
 * This class is a custom rule for rector tool (https://getrector.org/), see Upgrading.md
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\rector;

use PhpParser\Node;
use PhpParser\Node\{
    Arg,
    Expr,
    Name
};
use PhpParser\Node\Expr\{
    Array_,
    ConstFetch,
    New_
};
use PhpParser\Node\Scalar\String_;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\RectorDefinition\{
    CodeSample,
    RectorDefinition
};

/**
 * Replaces usage of FunctionCall() / FunctionExpression() with SQL keyword string as function name
 */
final class UseSpecializedNodesInsteadOfFunctionCallRector extends AbstractRector
{
    /**
     * {@inheritDoc}
     */
    public function getNodeTypes(): array
    {
        return [New_::class];
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Replaces FunctionCall\'s used with SQL keywords for function names to proper Nodes',
            [new CodeSample(
                <<<'CODE_SAMPLE'
use sad_spirit\pg_builder\nodes\expressions\FunctionExpression;
use sad_spirit\pg_builder\nodes\FunctionCall;
use sad_spirit\pg_builder\nodes\lists\FunctionArgumentList;

$all      = new FunctionCall('all', new FunctionArgumentList([$argument]));
$nullif   = new FunctionExpression('nullif', new FunctionArgumentList($arguments));
$coalesce = new FunctionExpression('coalesce', $coalesceArguments);
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
use sad_spirit\pg_builder\nodes\expressions\ArrayComparisonExpression;
use sad_spirit\pg_builder\nodes\expressions\NullIfExpression;
use sad_spirit\pg_builder\nodes\expressions\SystemFunctionCall;

$all      = new ArrayComparisonExpression('all', $argument);
$nullif   = new NullifExpression(...$arguments);
$coalesce = new SystemFunctionCall('coalesce', ...\iterator_to_array($coalesceArguments));
CODE_SAMPLE
            )]
        );
    }

    /**
     * {@inheritDoc}
     * @param New_ $node
     */
    public function refactor(Node $node): ?Node
    {
        if (
            !$this->isObjectType($node->class, 'sad_spirit\pg_builder\nodes\FunctionCall')
            || !$node->args[0]->value instanceof String_
        ) {
            return null;
        }

        switch ($node->args[0]->value->value) {
            case 'all':
            case 'any':
            case 'some':
                return $this->convertToArrayComparisonExpression($node);

            case 'nullif':
                return $this->convertToNullIfExpression($node);

            case 'coalesce':
            case 'greatest':
            case 'least':
            case 'xmlconcat':
                return $this->convertToSystemFunctionCall($node);
        }

        return null;
    }

    private function unpackFunctionArgumentList(Expr $node, int $maxArgs): array
    {
        $args = [];
        if (!$node instanceof New_) {
            // we assume this was an instance of FunctionArgumentList, just unpack it
            $args[] = new Arg($node, false, true);

        } else {
            // new FunctionArgumentList() - everything else is an error, so don't bother checking
            $newArg = $node->args[0]->value;
            if (!$newArg instanceof Array_) {
                // Can only be an array or Traversable, unpack
                $args[] = $node->args[0];
                $args[0]->unpack = true;
            } else {
                // Directly use array items in case of array literal
                for ($i = 0; $i < $maxArgs; $i++) {
                    if (isset($newArg->items[$i])) {
                        $args[] = new Arg($newArg->items[$i]->value);
                    } else {
                        $args[] = new Arg(new ConstFetch(new Name('null')));
                    }
                }
            }
        }

        return $args;
    }

    private function removeExtraArguments(New_ $node, int $expected): void
    {
        if (count($node->args) > $expected) {
            $node->args = array_slice($node->args, 0, $expected);
        }
    }

    private function convertToArrayComparisonExpression(New_ $node): New_
    {
        $node->class = new Name('\sad_spirit\pg_builder\nodes\expressions\ArrayComparisonExpression');
        $args = $this->unpackFunctionArgumentList($node->args[1]->value, 1);
        foreach ($args as $idx => $arg) {
            $node->args[$idx + 1] = $arg;
        }
        $this->removeExtraArguments($node, 2);
        return $node;
    }

    private function convertToNullIfExpression(New_ $node): New_
    {
        $node->class = new Name('\sad_spirit\pg_builder\nodes\expressions\NullIfExpression');
        array_shift($node->args);

        $args = $this->unpackFunctionArgumentList($node->args[0]->value, 2);
        foreach ($args as $idx => $arg) {
            $node->args[$idx] = $arg;
        }

        $this->removeExtraArguments($node, count($args));
        return $node;
    }

    private function convertToSystemFunctionCall(New_ $node): New_
    {
        $node->class = new Name('\sad_spirit\pg_builder\nodes\expressions\SystemFunctionCall');

        if ($node->args[1]->value instanceof New_) {
            // FunctionCall can only accept instance of FunctionArgumentList, replace that with ExpressionList
            $node->args[1]->value->class = new Name('\sad_spirit\pg_builder\nodes\lists\ExpressionList');
        } else {
            // wrap argument with ExpressionList, it will handle FunctionArgumentList
            $node->args[1]->value = new New_(
                new Name('\sad_spirit\pg_builder\nodes\lists\ExpressionList'),
                [new Arg($node->args[1]->value)]
            );
        }
        $this->removeExtraArguments($node, 2);
        return $node;
    }
}
