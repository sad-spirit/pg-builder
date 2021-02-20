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

namespace sad_spirit\pg_builder\tests\nodes;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\{
    Delete,
    Insert,
    Select,
    SetOpSelect,
    Update,
    Values
};
use sad_spirit\pg_builder\nodes\{
    ArrayIndexes,
    ColumnReference,
    CommonTableExpression,
    FunctionCall,
    Identifier,
    Indirection,
    IntervalTypeName,
    OrderByElement,
    QualifiedName,
    SetTargetElement,
    SingleSetClause,
    TargetElement,
    TypeName,
    WindowDefinition,
    WindowFrameBound,
    WithClause,
    WindowFrameClause
};
use sad_spirit\pg_builder\nodes\expressions\{
    BetweenExpression,
    CaseExpression,
    CollateExpression,
    InExpression,
    IsOfExpression,
    NamedParameter,
    NumericConstant,
    OperatorExpression,
    PatternMatchingExpression,
    StringConstant,
    SubselectExpression,
    TypecastExpression,
    WhenExpression
};
use sad_spirit\pg_builder\nodes\lists\{
    ColumnDefinitionList,
    RowList,
    ExpressionList,
    FunctionArgumentList,
    IdentifierList,
    OrderByList,
    SetClauseList,
    TargetList,
    TypeList,
    TypeModifierList
};
use sad_spirit\pg_builder\nodes\range\{
    ColumnDefinition,
    FunctionCall as RangeFunctionCall,
    InsertTarget,
    JoinExpression,
    RelationReference,
    Subselect,
    UpdateOrDeleteTarget
};
use sad_spirit\pg_builder\nodes\xml\{
    XmlElement,
    XmlParse,
    XmlPi,
    XmlRoot,
    XmlSerialize
};

/**
 * Tests that parent node is properly set on children
 */
class SetParentNodeTest extends TestCase
{
    public function testDeleteStatement(): void
    {
        $delete = new Delete(new UpdateOrDeleteTarget(new QualifiedName('foo', 'bar')));

        $this->assertSame($delete, $delete->relation->getParentNode());
        $this->assertSame($delete, $delete->using->getParentNode());
        $this->assertSame($delete, $delete->returning->getParentNode());
        $this->assertSame($delete, $delete->where->getParentNode());

        $withOne = new WithClause([], false);
        $withTwo = new WithClause([], true);

        $delete->setWith($withOne);
        $this->assertSame($delete, $withOne->getParentNode());
        $delete->setWith($withTwo);
        $this->assertSame($delete, $withTwo->getParentNode());
    }

    public function testInsertStatement(): void
    {
        $insert = new Insert(new InsertTarget(new QualifiedName('foo', 'bar')));

        $this->assertSame($insert, $insert->relation->getParentNode());
        $this->assertSame($insert, $insert->cols->getParentNode());
        $this->assertSame($insert, $insert->returning->getParentNode());

        $values = new Values(new RowList([[new NumericConstant('1')], [new NumericConstant('2')]]));
        $insert->setValues($values);
        $this->assertSame($insert, $values->getParentNode());
        $insert->setValues(null);
        $this->assertNull($values->getParentNode());
    }

    /**
     * @psalm-suppress PossiblyInvalidMethodCall
     */
    public function testSelectStatement(): void
    {
        $select = new Select(
            new TargetList([
                new TargetElement(new ColumnReference('foo')),
                new TargetElement(new ColumnReference('bar'))
            ]),
            new ExpressionList([new ColumnReference('foo')])
        );

        $this->assertSame($select, $select->list->getParentNode());
        $this->assertSame($select, $select->distinct->getParentNode());
        $this->assertSame($select, $select->from->getParentNode());
        $this->assertSame($select, $select->where->getParentNode());
        $this->assertSame($select, $select->group->getParentNode());
        $this->assertSame($select, $select->having->getParentNode());
        $this->assertSame($select, $select->window->getParentNode());
        $this->assertSame($select, $select->order->getParentNode());
        $this->assertSame($select, $select->locking->getParentNode());

        $distinct = $select->distinct;
        $select->distinct = false;
        $this->assertNull($distinct->getParentNode());

        $five  = new NumericConstant('5');

        $select->limit = $five;
        $this->assertSame($select, $five->getParentNode());
        $select->offset = $five;
        $this->assertNull($select->limit);
        $select->offset = null;
        $this->assertNull($five->getParentNode());
    }

    public function testSetOpSelectStatement(): void
    {
        $selectOne = new Select(new TargetList([new TargetElement(new StringConstant('foo'))]));
        $selectTwo = new Select(new TargetList([new TargetElement(new StringConstant('bar'))]));
        $setOp     = new SetOpSelect($selectOne, $selectTwo);

        $this->assertSame($setOp, $selectOne->getParentNode());
        $this->assertSame($setOp, $selectTwo->getParentNode());
    }

    public function testUpdateStatement(): void
    {
        $update = new Update(
            new UpdateOrDeleteTarget(new QualifiedName('foo', 'bar')),
            new SetClauseList([
                new SingleSetClause(
                    new SetTargetElement(new Identifier('baz')),
                    new StringConstant('quux')
                )
            ])
        );

        $this->assertSame($update, $update->relation->getParentNode());
        $this->assertSame($update, $update->set->getParentNode());
        $this->assertSame($update, $update->from->getParentNode());
        $this->assertSame($update, $update->where->getParentNode());
        $this->assertSame($update, $update->returning->getParentNode());
    }

    public function testValuesStatement(): void
    {
        $values = new Values(new RowList([[new NumericConstant('1')], [new NumericConstant('2')]]));

        $this->assertSame($values, $values->rows->getParentNode());
    }

    public function testArrayIndexes(): void
    {
        $indexes = new ArrayIndexes(new NumericConstant('1'), new NumericConstant('10'), true);

        $this->assertSame($indexes, $indexes->lower->getParentNode());
        $this->assertSame($indexes, $indexes->upper->getParentNode());
    }

    public function testColumnReference(): void
    {
        $ref = new ColumnReference('foo', 'bar', 'baz', 'quux');

        $this->assertSame($ref, $ref->catalog->getParentNode());
        $this->assertSame($ref, $ref->schema->getParentNode());
        $this->assertSame($ref, $ref->relation->getParentNode());
        $this->assertSame($ref, $ref->column->getParentNode());
    }

    public function testCommonTableExpression(): void
    {
        $cte = new CommonTableExpression(
            new Select(new TargetList([new TargetElement(new StringConstant('foo'))])),
            new Identifier('bar'),
            new IdentifierList(['baz', 'quux'])
        );

        $this->assertSame($cte, $cte->statement->getParentNode());
        $this->assertSame($cte, $cte->alias->getParentNode());
        $this->assertSame($cte, $cte->columnAliases->getParentNode());
    }

    public function testFunctionCall(): void
    {
        $fn = new FunctionCall(
            new QualifiedName('foo', 'bar'),
            new FunctionArgumentList([new NumericConstant('1')]),
            false,
            false,
            new OrderByList([new OrderByElement(new ColumnReference('baz'))])
        );

        $this->assertSame($fn, $fn->name->getParentNode());
        $this->assertSame($fn, $fn->arguments->getParentNode());
        $this->assertSame($fn, $fn->order->getParentNode());
    }

    public function testIndirection(): void
    {
        $indirection = new Indirection([new Identifier('foo')], new NamedParameter('bar'));

        $this->assertSame($indirection, $indirection->expression->getParentNode());
    }

    public function testOrderByElement(): void
    {
        $order = new OrderByElement(new ColumnReference('foo'), 'asc', 'last');

        $this->assertSame($order, $order->expression->getParentNode());
    }

    public function testQualifiedName(): void
    {
        $name = new QualifiedName('foo', 'bar', 'baz');

        $this->assertSame($name, $name->catalog->getParentNode());
        $this->assertSame($name, $name->schema->getParentNode());
        $this->assertSame($name, $name->relation->getParentNode());
    }

    public function testSetTargetElement(): void
    {
        $target = new SetTargetElement(
            new Identifier('baz'),
            [new Identifier('blah')]
        );

        $this->assertSame($target, $target->name->getParentNode());
    }

    public function testTargetElement(): void
    {
        $target = new TargetElement(
            new ColumnReference('foo', 'bar'),
            new Identifier('baz')
        );

        $this->assertSame($target, $target->expression->getParentNode());
        $this->assertSame($target, $target->alias->getParentNode());
    }

    public function testTypeName(): void
    {
        $typename = new TypeName(
            new QualifiedName('foo', 'bar'),
            new TypeModifierList([new NumericConstant('1')])
        );

        $this->assertSame($typename, $typename->name->getParentNode());
        $this->assertSame($typename, $typename->modifiers->getParentNode());

        $interval = new IntervalTypeName(new TypeModifierList([new NumericConstant('1')]));
        $this->assertSame($interval, $interval->modifiers->getParentNode());
    }

    public function testWindowDefinition(): void
    {
        $constant5  = new NumericConstant('5');
        $constant10 = new NumericConstant('10');
        $start      = new WindowFrameBound('preceding', $constant5);
        $end        = new WindowFrameBound('following', $constant10);

        $this->assertSame($start, $start->value->getParentNode());
        $end->setValue(null);
        $this->assertNull($constant10->getParentNode());

        $window = new WindowDefinition(
            new Identifier('reference'),
            new ExpressionList([new ColumnReference('foo')]),
            new OrderByList([new OrderByElement(new ColumnReference('bar'))]),
            $frame = new WindowFrameClause('rows', $start, $end)
        );

        $this->assertSame($window, $window->refName->getParentNode());
        $this->assertSame($window, $window->partition->getParentNode());
        $this->assertSame($window, $window->order->getParentNode());
        $this->assertSame($window, $window->frame->getParentNode());
        $this->assertSame($frame, $frame->start->getParentNode());
        $this->assertSame($frame, $frame->end->getParentNode());

        $name = new Identifier('myname');
        $window->name = $name;
        $this->assertSame($window, $name->getParentNode());

        $window->setName(null);
        $this->assertNull($name->getParentNode());
    }

    public function testBetweenExpression(): void
    {
        $between = new BetweenExpression(
            new ColumnReference('foo'),
            new NumericConstant('1'),
            new NumericConstant('2')
        );

        $this->assertSame($between, $between->argument->getParentNode());
        $this->assertSame($between, $between->left->getParentNode());
        $this->assertSame($between, $between->right->getParentNode());
    }

    public function testCaseExpression(): void
    {
        $case = new CaseExpression(
            [new WhenExpression(new ColumnReference('foo'), new StringConstant('foo'))],
            new NumericConstant('666'),
            new ColumnReference('bar')
        );

        $this->assertSame($case, $case->argument->getParentNode());
        $this->assertSame($case, $case->else->getParentNode());

        $this->assertSame($case[0], $case[0]->when->getParentNode());
        $this->assertSame($case[0], $case[0]->then->getParentNode());
    }

    public function testCollateExpression(): void
    {
        $collate = new CollateExpression(new ColumnReference('foo'), new QualifiedName('bar', 'baz'));

        $this->assertSame($collate, $collate->argument->getParentNode());
        $this->assertSame($collate, $collate->collation->getParentNode());
    }

    public function testInExpression(): void
    {
        $in = new InExpression(
            new ColumnReference('foo'),
            new ExpressionList([new StringConstant('foo'), new StringConstant('bar')])
        );

        $this->assertSame($in, $in->left->getParentNode());
        $this->assertSame($in, $in->right->getParentNode());
    }

    public function testIsOfExpression(): void
    {
        $isOf = new IsOfExpression(
            new ColumnReference('foo'),
            new TypeList([new TypeName(new QualifiedName('pg_catalog', 'text'))])
        );

        $this->assertSame($isOf, $isOf->left->getParentNode());
        $this->assertSame($isOf, $isOf->right->getParentNode());
    }

    public function testOperatorExpression(): void
    {
        $operator = new OperatorExpression(
            '=',
            new ColumnReference('foo'),
            new StringConstant('foo')
        );

        $this->assertSame($operator, $operator->left->getParentNode());
        $this->assertSame($operator, $operator->right->getParentNode());
    }

    public function testPatternMatchingExpression(): void
    {
        $pattern = new PatternMatchingExpression(
            new ColumnReference('foo'),
            new StringConstant('blah%'),
            'like',
            false,
            new StringConstant('!')
        );

        $this->assertSame($pattern, $pattern->argument->getParentNode());
        $this->assertSame($pattern, $pattern->pattern->getParentNode());
        $this->assertSame($pattern, $pattern->escape->getParentNode());
    }

    public function testSubselectExpression(): void
    {
        $subselect = new SubselectExpression(
            new Select(new TargetList([new TargetElement(new ColumnReference('foo'))]))
        );

        $this->assertSame($subselect, $subselect->query->getParentNode());
    }

    public function testTypecastExpression(): void
    {
        $typecast = new TypecastExpression(
            new ColumnReference('foo'),
            new TypeName(new QualifiedName('bar', 'baz'))
        );

        $this->assertSame($typecast, $typecast->argument->getParentNode());
        $this->assertSame($typecast, $typecast->type->getParentNode());
    }

    public function testColumnDefinition(): void
    {
        $colDef = new ColumnDefinition(
            new Identifier('blah'),
            new TypeName(new QualifiedName('foo', 'bar')),
            new QualifiedName('weirdcollation')
        );

        $this->assertSame($colDef, $colDef->name->getParentNode());
        $this->assertSame($colDef, $colDef->type->getParentNode());
        $this->assertSame($colDef, $colDef->collation->getParentNode());
    }

    public function testRelationReference(): void
    {
        $ref = new RelationReference(new QualifiedName('foo', 'bar'));

        $this->assertSame($ref, $ref->name->getParentNode());

        $tableAlias    = new Identifier('blah');
        $columnAliases = new IdentifierList(['baz', 'quux']);

        $ref->setAlias($tableAlias, $columnAliases);
        $this->assertSame($ref, $tableAlias->getParentNode());
        $this->assertSame($ref, $columnAliases->getParentNode());

        $ref->setAlias(null, null);
        $this->assertNull($tableAlias->getParentNode());
        $this->assertNull($columnAliases->getParentNode());
    }

    public function testRangeFunctionCall(): void
    {
        $fn = new RangeFunctionCall(new FunctionCall(
            new QualifiedName('foo', 'bar'),
            new FunctionArgumentList([new NumericConstant('1')])
        ));

        $this->assertSame($fn, $fn->function->getParentNode());

        $tableAlias    = new Identifier('blah');
        $columnAliases = new ColumnDefinitionList([
            new ColumnDefinition(
                new Identifier('blahtext'),
                new TypeName(new QualifiedName('pg_catalog', 'text'))
            )
        ]);

        $fn->setAlias($tableAlias, $columnAliases);
        $this->assertSame($fn, $tableAlias->getParentNode());
        $this->assertSame($fn, $columnAliases->getParentNode());
    }

    public function testJoinExpression(): void
    {
        $join = new JoinExpression(
            new RelationReference(new QualifiedName('foo', 'bar')),
            new RelationReference(new QualifiedName('baz', 'quux'))
        );

        $this->assertSame($join, $join->left->getParentNode());
        $this->assertSame($join, $join->right->getParentNode());

        $using = new IdentifierList(['one', 'two', 'three']);
        $join->setUsing($using);
        $this->assertSame($join, $using->getParentNode());

        $join->using = null;
        $this->assertNull($using->getParentNode());
        $join->setOn(new OperatorExpression('=', new ColumnReference('one'), new ColumnReference('two')));
        $this->assertSame($join, $join->on->getParentNode());
    }

    public function testRangeSubselect(): void
    {
        $subselect = new Subselect(
            new Select(new TargetList([new TargetElement(new StringConstant('foo'))]))
        );

        $this->assertSame($subselect, $subselect->query->getParentNode());
    }

    public function testXmlElement(): void
    {
        $xml = new XmlElement(
            new Identifier('name'),
            new TargetList([new TargetElement(new StringConstant('attvalue'), new Identifier('attname'))]),
            new ExpressionList([new StringConstant('stuff')])
        );

        $this->assertSame($xml, $xml->name->getParentNode());
        $this->assertSame($xml, $xml->attributes->getParentNode());
        $this->assertSame($xml, $xml->content->getParentNode());
    }

    public function testXmlParse(): void
    {
        $xml = new XmlParse('document', new StringConstant('<foo>bar</foo>'), false);

        $this->assertSame($xml, $xml->argument->getParentNode());
    }

    public function testXmlPi(): void
    {
        $xml = new XmlPi(new Identifier('php'), new StringConstant("echo 'Hello world!';"));

        $this->assertSame($xml, $xml->content->getParentNode());
        $this->assertSame($xml, $xml->name->getParentNode());
    }

    public function testXmlRoot(): void
    {
        $xml = new XmlRoot(new ColumnReference('doc'), new StringConstant('1.2'), 'yes');

        $this->assertSame($xml, $xml->xml->getParentNode());
        $this->assertSame($xml, $xml->version->getParentNode());
    }

    public function testXmlSerialize(): void
    {
        $xml = new XmlSerialize(
            'document',
            new ColumnReference('foo'),
            new TypeName(new QualifiedName('pg_catalog', 'text'))
        );

        $this->assertSame($xml, $xml->argument->getParentNode());
        $this->assertSame($xml, $xml->type->getParentNode());
    }
}
