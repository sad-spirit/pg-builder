<?php

/**
 * Query builder for PostgreSQL backed by a query parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
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
    Constant,
    FunctionCall,
    Identifier,
    Indirection,
    IntervalTypeName,
    OrderByElement,
    Parameter,
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
    OperatorExpression,
    PatternMatchingExpression,
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
    public function testDeleteStatement()
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

    public function testInsertStatement()
    {
        $insert = new Insert(new InsertTarget(new QualifiedName('foo', 'bar')));

        $this->assertSame($insert, $insert->relation->getParentNode());
        $this->assertSame($insert, $insert->cols->getParentNode());
        $this->assertSame($insert, $insert->returning->getParentNode());

        $values = new Values(new RowList([[new Constant(1)], [new Constant(2)]]));
        $insert->setValues($values);
        $this->assertSame($insert, $values->getParentNode());
        $insert->setValues(null);
        $this->assertNull($values->getParentNode());
    }

    public function testSelectStatement()
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

        $five  = new Constant(5);

        $select->limit = $five;
        $this->assertSame($select, $five->getParentNode());
        $select->offset = $five;
        $this->assertNull($select->limit);
        $select->offset = null;
        $this->assertNull($five->getParentNode());
    }

    public function testSetOpSelectStatement()
    {
        $selectOne = new Select(new TargetList([new TargetElement(new Constant('foo'))]));
        $selectTwo = new Select(new TargetList([new TargetElement(new Constant('bar'))]));
        $setOp     = new SetOpSelect($selectOne, $selectTwo);

        $this->assertSame($setOp, $selectOne->getParentNode());
        $this->assertSame($setOp, $selectTwo->getParentNode());
    }

    public function testUpdateStatement()
    {
        $update = new Update(
            new UpdateOrDeleteTarget(new QualifiedName('foo', 'bar')),
            new SetClauseList([
                new SingleSetClause(
                    new SetTargetElement(new Identifier('baz')),
                    new Constant('quux')
                )
            ])
        );

        $this->assertSame($update, $update->relation->getParentNode());
        $this->assertSame($update, $update->set->getParentNode());
        $this->assertSame($update, $update->from->getParentNode());
        $this->assertSame($update, $update->where->getParentNode());
        $this->assertSame($update, $update->returning->getParentNode());
    }

    public function testValuesStatement()
    {
        $values = new Values(new RowList([[new Constant(1)], [new Constant(2)]]));

        $this->assertSame($values, $values->rows->getParentNode());
    }

    public function testArrayIndexes()
    {
        $indexes = new ArrayIndexes(new Constant(1), new Constant(10));

        $this->assertSame($indexes, $indexes->lower->getParentNode());
        $this->assertSame($indexes, $indexes->upper->getParentNode());
    }

    public function testColumnReference()
    {
        $ref = new ColumnReference('foo', 'bar', 'baz', 'quux');

        $this->assertSame($ref, $ref->catalog->getParentNode());
        $this->assertSame($ref, $ref->schema->getParentNode());
        $this->assertSame($ref, $ref->relation->getParentNode());
        $this->assertSame($ref, $ref->column->getParentNode());
    }

    public function testCommonTableExpression()
    {
        $cte = new CommonTableExpression(
            new Select(new TargetList([new TargetElement(new Constant('foo'))])),
            new Identifier('bar'),
            new IdentifierList(['baz', 'quux'])
        );

        $this->assertSame($cte, $cte->statement->getParentNode());
        $this->assertSame($cte, $cte->alias->getParentNode());
        $this->assertSame($cte, $cte->columnAliases->getParentNode());
    }

    public function testFunctionCall()
    {
        $fn = new FunctionCall(
            new QualifiedName('foo', 'bar'),
            new FunctionArgumentList([new Constant(1)]),
            false,
            false,
            new OrderByList([new OrderByElement(new ColumnReference('baz'))])
        );

        $this->assertSame($fn, $fn->name->getParentNode());
        $this->assertSame($fn, $fn->arguments->getParentNode());
        $this->assertSame($fn, $fn->order->getParentNode());
    }

    public function testIndirection()
    {
        $indirection = new Indirection([new Identifier('foo')], new Parameter('bar'));

        $this->assertSame($indirection, $indirection->expression->getParentNode());
    }

    public function testOrderByElement()
    {
        $order = new OrderByElement(new ColumnReference('foo'), 'asc', 'last');

        $this->assertSame($order, $order->expression->getParentNode());
    }

    public function testQualifiedName()
    {
        $name = new QualifiedName('foo', 'bar', 'baz');

        $this->assertSame($name, $name->catalog->getParentNode());
        $this->assertSame($name, $name->schema->getParentNode());
        $this->assertSame($name, $name->relation->getParentNode());
    }

    public function testSetTargetElement()
    {
        $target = new SetTargetElement(
            new Identifier('baz'),
            [new Identifier('blah')]
        );

        $this->assertSame($target, $target->name->getParentNode());
    }

    public function testTargetElement()
    {
        $target = new TargetElement(
            new ColumnReference('foo', 'bar'),
            new Identifier('baz')
        );

        $this->assertSame($target, $target->expression->getParentNode());
        $this->assertSame($target, $target->alias->getParentNode());
    }

    public function testTypeName()
    {
        $typename = new TypeName(
            new QualifiedName('foo', 'bar'),
            new TypeModifierList([new Constant(1)])
        );

        $this->assertSame($typename, $typename->name->getParentNode());
        $this->assertSame($typename, $typename->modifiers->getParentNode());

        $interval = new IntervalTypeName(new TypeModifierList([new Constant(1)]));
        $this->assertSame($interval, $interval->modifiers->getParentNode());
    }

    public function testWindowDefinition()
    {
        $constant5  = new Constant(5);
        $constant10 = new Constant(10);
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

    public function testBetweenExpression()
    {
        $between = new BetweenExpression(new ColumnReference('foo'), new Constant(1), new Constant(2));

        $this->assertSame($between, $between->argument->getParentNode());
        $this->assertSame($between, $between->left->getParentNode());
        $this->assertSame($between, $between->right->getParentNode());
    }

    public function testCaseExpression()
    {
        $case = new CaseExpression(
            [new WhenExpression(new ColumnReference('foo'), new Constant('foo'))],
            new Constant(666),
            new ColumnReference('bar')
        );

        $this->assertSame($case, $case->argument->getParentNode());
        $this->assertSame($case, $case->else->getParentNode());

        $this->assertSame($case[0], $case[0]->when->getParentNode());
        $this->assertSame($case[0], $case[0]->then->getParentNode());
    }

    public function testCollateExpression()
    {
        $collate = new CollateExpression(new ColumnReference('foo'), new QualifiedName('bar', 'baz'));

        $this->assertSame($collate, $collate->argument->getParentNode());
        $this->assertSame($collate, $collate->collation->getParentNode());
    }

    public function testInExpression()
    {
        $in = new InExpression(
            new ColumnReference('foo'),
            new ExpressionList([new Constant('foo'), new Constant('bar')])
        );

        $this->assertSame($in, $in->left->getParentNode());
        $this->assertSame($in, $in->right->getParentNode());
    }

    public function testIsOfExpression()
    {
        $isOf = new IsOfExpression(
            new ColumnReference('foo'),
            new TypeList([new TypeName(new QualifiedName('pg_catalog', 'text'))])
        );

        $this->assertSame($isOf, $isOf->left->getParentNode());
        $this->assertSame($isOf, $isOf->right->getParentNode());
    }

    public function testOperatorExpression()
    {
        $operator = new OperatorExpression(
            '=',
            new ColumnReference('foo'),
            new Constant('foo')
        );

        $this->assertSame($operator, $operator->left->getParentNode());
        $this->assertSame($operator, $operator->right->getParentNode());
    }

    public function testPatternMatchingExpression()
    {
        $pattern = new PatternMatchingExpression(
            new ColumnReference('foo'),
            new Constant('blah%'),
            'like',
            false,
            new Constant('!')
        );

        $this->assertSame($pattern, $pattern->argument->getParentNode());
        $this->assertSame($pattern, $pattern->pattern->getParentNode());
        $this->assertSame($pattern, $pattern->escape->getParentNode());
    }

    public function testSubselectExpression()
    {
        $subselect = new SubselectExpression(
            new Select(new TargetList([new TargetElement(new ColumnReference('foo'))]))
        );

        $this->assertSame($subselect, $subselect->query->getParentNode());
    }

    public function testTypecastExpression()
    {
        $typecast = new TypecastExpression(
            new ColumnReference('foo'),
            new TypeName(new QualifiedName('bar', 'baz'))
        );

        $this->assertSame($typecast, $typecast->argument->getParentNode());
        $this->assertSame($typecast, $typecast->type->getParentNode());
    }

    public function testColumnDefinition()
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

    public function testRelationReference()
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

    public function testRangeFunctionCall()
    {
        $fn = new RangeFunctionCall(new FunctionCall(
            new QualifiedName('foo', 'bar'),
            new FunctionArgumentList([new Constant(1)])
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

    public function testJoinExpression()
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

    public function testRangeSubselect()
    {
        $subselect = new Subselect(
            new Select(new TargetList([new TargetElement(new Constant('foo'))]))
        );

        $this->assertSame($subselect, $subselect->query->getParentNode());
    }

    public function testXmlElement()
    {
        $xml = new XmlElement(
            new Identifier('name'),
            new TargetList([new TargetElement(new Constant('attvalue'), new Identifier('attname'))]),
            new ExpressionList([new Constant('stuff')])
        );

        $this->assertSame($xml, $xml->name->getParentNode());
        $this->assertSame($xml, $xml->attributes->getParentNode());
        $this->assertSame($xml, $xml->content->getParentNode());
    }

    public function testXmlParse()
    {
        $xml = new XmlParse('document', new Constant('<foo>bar</foo>'), false);

        $this->assertSame($xml, $xml->argument->getParentNode());
    }

    public function testXmlPi()
    {
        $xml = new XmlPi(new Identifier('php'), new Constant("echo 'Hello world!';"));

        $this->assertSame($xml, $xml->content->getParentNode());
        $this->assertSame($xml, $xml->name->getParentNode());
    }

    public function testXmlRoot()
    {
        $xml = new XmlRoot(new ColumnReference('doc'), new Constant('1.2'), 'yes');

        $this->assertSame($xml, $xml->xml->getParentNode());
        $this->assertSame($xml, $xml->version->getParentNode());
    }

    public function testXmlSerialize()
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
