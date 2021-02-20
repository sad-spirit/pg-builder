<?php

/**
 * This class is a custom rule for rector tool (https://getrector.org/), see Upgrading.md
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\rector;

use PhpParser\Node;
use PhpParser\Node\{
    Expr\New_,
    Expr\StaticCall,
    Name
};
use Rector\Core\{
    Rector\AbstractRector,
    RectorDefinition\CodeSample,
    RectorDefinition\RectorDefinition
};

/**
 * Use newly-introduced specialized subclasses of Parameter node
 */
final class NewParameterToFactoryMethodAndSubclassesRector extends AbstractRector
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
            'Replaces creation of Parameter instances with its child classes, fixes namespace',
            [new CodeSample(
                <<<'CODE_SAMPLE'
$parameterOne   = new \sad_spirit\pg_builder\nodes\Parameter(1);
$parameterFoo   = new \sad_spirit\pg_builder\nodes\Parameter('foo');
$parameterToken = new \sad_spirit\pg_builder\nodes\Parameter($token);
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
$parameterOne   = new \sad_spirit\pg_builder\nodes\expressions\PositionalParameter(1);
$parameterFoo   = new \sad_spirit\pg_builder\nodes\expressions\NamedParameter('foo');
$parameterToken = new \sad_spirit\pg_builder\nodes\expressions\Parameter::createFromToken($token);
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
        if (!$this->isObjectType($node->class, 'sad_spirit\pg_builder\nodes\Parameter')) {
            return null;
        }

        if ($this->isObjectType($node->args[0]->value, 'sad_spirit\pg_builder\Token')) {
            return new StaticCall(
                new Name('\sad_spirit\pg_builder\nodes\expressions\Parameter'),
                'createFromToken',
                $node->args
            );

        } elseif ($this->isNumberType($node->args[0]->value)) {
            $node->class = new Name('\sad_spirit\pg_builder\nodes\expressions\PositionalParameter');

        } else {
            $node->class = new Name('\sad_spirit\pg_builder\nodes\expressions\NamedParameter');
        }

        return $node;
    }
}
