# Upgrading

## From 0.4.x to 1.0.0

Some changes to `Node`s' API may require the extensive rewrite of code using the changed `Node`s.

You can use the [rector tool] to automate the following:
 * Renames of methods and properties
 * Replacing creation of `Constant` and `Parameter` instances with either factory method calls or creation of specialized subclasses instances.
 * Different arguments to `StatementFactory` constructor and addition of `StatementFactory::forConnection()`
 * Changed signatures of constructors for
    * `QualifiedName`,
    * `ColumnReference`,
    * `BetweenExpression`,
    * `InExpression`,
    * `IsOfExpression`,
    * `PatternMatchingExpression`.
 * Addition of several specialized Nodes that should be used instead of `OperatorExpression`
 * Addition of Nodes that should be used instead of `FunctionCall` / `FunctionExpression` with SQL keyword string as function name

Fixing constructors for `Expression` classes and replacing `OperatorExpression` will only work if the operator(-like) 
constructor argument is a string literal. Other changes should hopefully work everytime.

Following are the additions to `rector.php` config file that are needed to perform the changes:

```PHP
use Rector\Renaming\Rector\{
    MethodCall\RenameMethodRector,
    Name\RenameClassRector,
    PropertyFetch\RenamePropertyRector
};
use Rector\Renaming\ValueObject\{
    MethodCallRename,
    RenameProperty
};
use sad_spirit\pg_builder\NativeStatement;
use sad_spirit\pg_builder\nodes\WhereOrHavingClause;
use sad_spirit\pg_builder\nodes\range\RowsFrom;
use sad_spirit\pg_builder\rector\{
    ArrayConstructorArgumentToVariadicRector,
    ChangeConstructorOfStatementFactoryRector,
    NegatedExpressionsStreamlineRector,
    NewConstantToFactoryMethodsAndSubclassesRector,
    NewParameterToFactoryMethodAndSubclassesRector,
    UseSpecializedNodesInsteadOfFunctionCallRector,
    UseSpecializedNodesInsteadOfOperatorExpressionRector
};
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Rector\SymfonyPhpConfig\inline_value_objects;

return static function (ContainerConfigurator $containerConfigurator): void {

    $services = $containerConfigurator->services();
    $services->set(ArrayConstructorArgumentToVariadicRector::class);
    $services->set(ChangeConstructorOfStatementFactoryRector::class);
    $services->set(NegatedExpressionsStreamlineRector::class);
    $services->set(NewConstantToFactoryMethodsAndSubclassesRector::class);
    $services->set(NewParameterToFactoryMethodAndSubclassesRector::class);
    $services->set(UseSpecializedNodesInsteadOfFunctionCallRector::class);
    $services->set(UseSpecializedNodesInsteadOfOperatorExpressionRector::class);

    $services->set(RenameMethodRector::class)
        ->call('configure', [[
            RenameMethodRector::METHOD_CALL_RENAMES => inline_value_objects(
                [
                    new MethodCallRename(NativeStatement::class, 'mergeInputTypes', 'mergeParameterTypes'),
                    new MethodCallRename(WhereOrHavingClause::class, 'and_', 'and'),
                    new MethodCallRename(WhereOrHavingClause::class, 'or_', 'or'),
                ]
            ),
        ]]);
    $services->set(RenameClassRector::class)
        ->call('configure', [[
            RenameClassRector::OLD_TO_NEW_CLASSES => [
                'sad_spirit\pg_builder\nodes\Constant'  => 'sad_spirit\pg_builder\nodes\expressions\Constant',
                'sad_spirit\pg_builder\nodes\Parameter' => 'sad_spirit\pg_builder\nodes\expressions\Parameter',
            ],
        ]]);
    $services->set(RenamePropertyRector::class)
            ->call('configure', [[
                RenamePropertyRector::RENAMED_PROPERTIES => inline_value_objects([
                    new RenameProperty(RowsFrom::class, 'function', 'functions'),
                ]),
            ]]);
    // Project specific config follows...
};
```

[rector tool]: https://getrector.org/