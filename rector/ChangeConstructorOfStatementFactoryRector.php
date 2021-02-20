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
use Rector\Core\Rector\AbstractRector;
use Rector\Core\RectorDefinition\{
    CodeSample,
    RectorDefinition
};

/**
 * Updates parameters to "new StatementFactory()" and / or changes it to "StatementFactory::forConnection"
 */
final class ChangeConstructorOfStatementFactoryRector extends AbstractRector
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
            'Updates "new StatementFactory" calls for new API',
            [new CodeSample(
                <<<'CODE_SAMPLE'
use sad_spirit\pg_builder\StatementFactory;

$factoryWithParser    = new StatementFactory(null, $parser);
$factoryForConnection = new StatementFactory($connection, null);
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
use sad_spirit\pg_builder\StatementFactory;

$factoryWithParser    = new StatementFactory($parser);
$factoryForConnection = StatementFactory::forConnection($connection);
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
        if (!$this->isObjectType($node->class, 'sad_spirit\pg_builder\StatementFactory')) {
            return null;
        }

        if ($this->isNull($node->args[0]->value)) {
            // Connection object is null? Remove, whatever is left is maybe parser
            array_shift($node->args);
            return $node;
        } else {
            return new StaticCall(
                new Name('\sad_spirit\pg_builder\StatementFactory'),
                'forConnection',
                [$node->args[0]]
            );
        }
    }
}
