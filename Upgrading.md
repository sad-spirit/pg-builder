# Upgrading

## From 0.4.x to 1.0.0

Some changes to `Node`s' API require the extensive rewrite of code using the changed `Node`s.

You can use the [rector tool] to automate the following:
 * Renaming of `and_()` and `or_()` methods of `WhereOrHavingClause` to versions without trailing underscore.
 * Changed signatures of constructors for 
    * `QualifiedName`,
    * `ColumnReference`,
    * `BetweenExpression`,
    * `InExpression`,
    * `IsOfExpression`,
    * `PatternMatchingExpression`.
 * Addition of several specialized Nodes that should be used instead of `OperatorExpression`

Fixing constructors for `Expression` classes and replacing `OperatorExpression` will only work if the operator(-like) 
constructor argument is a string literal. Other changes should hopefully work everytime.

Following are the additions to `rector.php` config file that are needed to perform the changes: 

```PHP
use Rector\Renaming\Rector\MethodCall\RenameMethodRector;
use Rector\Renaming\ValueObject\MethodCallRename;
use sad_spirit\pg_builder\nodes\WhereOrHavingClause;
use sad_spirit\pg_builder\rector\ArrayConstructorArgumentToVariadicRector;
use sad_spirit\pg_builder\rector\NegatedExpressionsStreamlineRector;
use sad_spirit\pg_builder\rector\UseSpecializedNodesInsteadOfOperatorExpressionRector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Rector\SymfonyPhpConfig\inline_value_objects;

return static function (ContainerConfigurator $containerConfigurator): void {

    $services = $containerConfigurator->services();
    $services->set(ArrayConstructorArgumentToVariadicRector::class);
    $services->set(NegatedExpressionsStreamlineRector::class);
    $services->set(UseSpecializedNodesInsteadOfOperatorExpressionRector::class);

    $services->set(RenameMethodRector::class)
        ->call('configure', [[
            RenameMethodRector::METHOD_CALL_RENAMES => inline_value_objects(
                [
                    new MethodCallRename(WhereOrHavingClause::class, 'and_', 'and'),
                    new MethodCallRename(WhereOrHavingClause::class, 'or_', 'or'),
                ]
            ),
        ]]);

    // Project specific config follows...
};
```

[rector tool]: https://getrector.org/