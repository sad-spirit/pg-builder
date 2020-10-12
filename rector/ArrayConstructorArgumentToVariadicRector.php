<?php

/**
 * This class is a custom rule for rector tool (https://getrector.org/), see Upgrading.md
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\rector;

use PhpParser\Node;
use PhpParser\Node\{
    Arg,
    Expr\Array_,
    Expr\ArrayItem,
    Expr\New_
};
use Rector\Core\{
    Rector\AbstractRector,
    RectorDefinition\CodeSample,
    RectorDefinition\RectorDefinition
};

/**
 * Replaces array arguments for ColumnDefinition and QualifiedName nodes by variadic
 * https://github.com/sad-spirit/pg-builder/issues/7
 */
final class ArrayConstructorArgumentToVariadicRector extends AbstractRector
{
    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            "Converts array arguments for QualifiedName and ColumnReference constructors to variadic",
            [new CodeSample(
                <<<'CODE_SAMPLE'
use sad_spirit\pg_builder\nodes\QualifiedName;
use sad_spirit\pg_builder\nodes\ColumnReference;

$table   = new QualifiedName(['pg_catalog', 'pg_type']);
$column  = new ColumnReference(array('pg_catalog', 'pg_type', 'oid'));
$dynamic = new QualifiedName($name);
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
use sad_spirit\pg_builder\nodes\QualifiedName;
use sad_spirit\pg_builder\nodes\ColumnReference;

$table   = new QualifiedName('pg_catalog', 'pg_type');
$column  = new ColumnReference('pg_catalog', 'pg_type', 'oid');
$dynamic = new QualifiedName(...$name);
CODE_SAMPLE
            )]
        );
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [New_::class];
    }

    /**
     * @inheritDoc
     * @param New_ $node
     */
    public function refactor(Node $node): ?Node
    {
        if (
            !$this->isObjectTypes(
                $node->class,
                ['sad_spirit\pg_builder\nodes\QualifiedName', 'sad_spirit\pg_builder\nodes\ColumnReference']
            )
            || 1 !== count($node->args)
        ) {
            return null;
        }

        if (!$node->args[0]->value instanceof Array_) {
            $node->args[0]->unpack = true;
        } else {
            $newArgs = array_map(function (ArrayItem $item): Arg {
                return new Arg($item);
            }, $node->args[0]->value->items);

            $node->args = $newArgs;
        }

        return $node;
    }
}
