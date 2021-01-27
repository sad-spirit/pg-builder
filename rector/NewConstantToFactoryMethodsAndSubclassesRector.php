<?php

/**
 * This class is a custom rule for rector tool (https://getrector.org/), see Upgrading.md
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\rector;

use PhpParser\Node;
use PhpParser\Node\{
    Expr\ClassConstFetch,
    Expr\New_,
    Expr\StaticCall,
    Name,
    Scalar\DNumber,
    Scalar\LNumber,
    Scalar\String_
};
use Rector\Core\{
    Rector\AbstractRector,
    RectorDefinition\CodeSample,
    RectorDefinition\RectorDefinition
};

/**
 * Use newly-introduced specialized subclasses of Constant node
 */
final class NewConstantToFactoryMethodsAndSubclassesRector extends AbstractRector
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
            'Replaces creation of Constant instances with factory-like calls, fixes namespace',
            [new CodeSample(
                <<<'CODE_SAMPLE'
$constantFromValue = new \sad_spirit\pg_builder\nodes\Constant($value);
$constantFromToken = new \sad_spirit\pg_builder\nodes\Constant($token);
$stringConstant    = new \sad_spirit\pg_builder\nodes\Constant('foo');
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
$constantFromValue = \sad_spirit\pg_builder\nodes\expressions\Constant::createFromPHPValue($value);
$constantFromToken = \sad_spirit\pg_builder\nodes\expressions\Constant::createFromToken($token);
$stringConstant    = new \sad_spirit\pg_builder\nodes\expressions\StringConstant('foo');
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
        if (!$this->isObjectType($node->class, 'sad_spirit\pg_builder\nodes\Constant')) {
            return null;
        }

        if ($this->isObjectType($node->args[0]->value, 'sad_spirit\pg_builder\Token')) {
            return new StaticCall(
                new Name('\sad_spirit\pg_builder\nodes\expressions\Constant'),
                'createFromToken',
                $node->args
            );

        } elseif ($this->isNull($node->args[0]->value)) {
            $node->class = new Name('\sad_spirit\pg_builder\nodes\expressions\KeywordConstant');
            $node->args[0]->value = new ClassConstFetch(
                new Name('\sad_spirit\pg_builder\nodes\expressions\KeywordConstant'),
                'NULL'
            );

        } elseif ($this->isStringOrUnionStringOnlyType($node->args[0]->value)) {
            $node->class = new Name('\sad_spirit\pg_builder\nodes\expressions\StringConstant');

        } elseif ($this->isNumberType($node->args[0]->value)) {
            $node->class = new Name('\sad_spirit\pg_builder\nodes\expressions\NumericConstant');
            if ($node->args[0]->value instanceof LNumber) {
                $node->args[0]->value = new String_((string)$node->args[0]->value->value);
            } elseif ($node->args[0]->value instanceof DNumber) {
                $node->args[0]->value = new String_(str_replace(',', '.', (string)$node->args[0]->value->value));
            }

        } else {
            return new StaticCall(
                new Name('\sad_spirit\pg_builder\nodes\expressions\Constant'),
                'createFromPHPValue',
                $node->args
            );
        }

        return $node;
    }
}
