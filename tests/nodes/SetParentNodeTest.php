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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\tests\nodes;

use sad_spirit\pg_builder\Delete,
    sad_spirit\pg_builder\Insert,
    sad_spirit\pg_builder\Select,
    sad_spirit\pg_builder\SetOpSelect,
    sad_spirit\pg_builder\Update,
    sad_spirit\pg_builder\Values,
    sad_spirit\pg_builder\nodes\ArrayIndexes,
    sad_spirit\pg_builder\nodes\ColumnReference,
    sad_spirit\pg_builder\nodes\CommonTableExpression,
    sad_spirit\pg_builder\nodes\Constant,
    sad_spirit\pg_builder\nodes\FunctionCall,
    sad_spirit\pg_builder\nodes\Identifier,
    sad_spirit\pg_builder\nodes\Indirection,
    sad_spirit\pg_builder\nodes\IntervalTypeName,
    sad_spirit\pg_builder\nodes\OrderByElement,
    sad_spirit\pg_builder\nodes\Parameter,
    sad_spirit\pg_builder\nodes\QualifiedName,
    sad_spirit\pg_builder\nodes\SetTargetElement,
    sad_spirit\pg_builder\nodes\TargetElement,
    sad_spirit\pg_builder\nodes\TypeName,
    sad_spirit\pg_builder\nodes\WindowDefinition,
    sad_spirit\pg_builder\nodes\WindowFrameBound,
    sad_spirit\pg_builder\nodes\WithClause,
    sad_spirit\pg_builder\nodes\expressions\BetweenExpression,
    sad_spirit\pg_builder\nodes\expressions\CaseExpression,
    sad_spirit\pg_builder\nodes\expressions\CollateExpression,
    sad_spirit\pg_builder\nodes\expressions\InExpression,
    sad_spirit\pg_builder\nodes\expressions\IsOfExpression,
    sad_spirit\pg_builder\nodes\expressions\OperatorExpression,
    sad_spirit\pg_builder\nodes\expressions\PatternMatchingExpression,
    sad_spirit\pg_builder\nodes\expressions\SubselectExpression,
    sad_spirit\pg_builder\nodes\expressions\TypecastExpression,
    sad_spirit\pg_builder\nodes\expressions\WhenExpression,
    sad_spirit\pg_builder\nodes\lists\ColumnDefinitionList,
    sad_spirit\pg_builder\nodes\lists\CtextRowList,
    sad_spirit\pg_builder\nodes\lists\ExpressionList,
    sad_spirit\pg_builder\nodes\lists\FunctionArgumentList,
    sad_spirit\pg_builder\nodes\lists\IdentifierList,
    sad_spirit\pg_builder\nodes\lists\OrderByList,
    sad_spirit\pg_builder\nodes\lists\SetTargetList,
    sad_spirit\pg_builder\nodes\lists\TargetList,
    sad_spirit\pg_builder\nodes\lists\TypeList,
    sad_spirit\pg_builder\nodes\lists\TypeModifierList,
    sad_spirit\pg_builder\nodes\range\ColumnDefinition,
    sad_spirit\pg_builder\nodes\range\FunctionCall as RangeFunctionCall,
    sad_spirit\pg_builder\nodes\range\JoinExpression,
    sad_spirit\pg_builder\nodes\range\RelationReference,
    sad_spirit\pg_builder\nodes\range\Subselect,
    sad_spirit\pg_builder\nodes\xml\XmlElement,
    sad_spirit\pg_builder\nodes\xml\XmlParse,
    sad_spirit\pg_builder\nodes\xml\XmlPi,
    sad_spirit\pg_builder\nodes\xml\XmlRoot,
    sad_spirit\pg_builder\nodes\xml\XmlSerialize;

/**
 * Tests that parent node is properly set on children
 */
class SetParentNodeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \sad_spirit\pg_builder\exceptions\InvalidArgumentException
     * @expectedExceptionMessage Cannot set a Node or its descendant as its own parent
     */
    public function testCannotCreateCycles()
    {
        $select = new Select(new TargetList(array(new TargetElement(new Constant('foo')))));
        $select->where->setCondition(new OperatorExpression(
            '=', new ColumnReference(array('foo')), new SubselectExpression($select, 'any')
        ));
    }

    public function testDeleteStatement()
    {
        $delete = new Delete(new RelationReference(new QualifiedName(array('foo', 'bar'))));

        $this->assertSame($delete, $delete->relation->getParentNode());
        $this->assertSame($delete, $delete->using->getParentNode());
        $this->assertSame($delete, $delete->returning->getParentNode());
        $this->assertSame($delete, $delete->where->getParentNode());

        $withOne = new WithClause(array(), false);
        $withTwo = new WithClause(array(), true);

        $delete->setWith($withOne);
        $this->assertSame($delete, $withOne->getParentNode());
        $delete->setWith($withTwo);
        $this->assertSame($delete, $withTwo->getParentNode());
    }

    public function testInsertStatement()
    {
        $insert = new Insert(new QualifiedName(array('foo', 'bar')));

        $this->assertSame($insert, $insert->relation->getParentNode());
        $this->assertSame($insert, $insert->cols->getParentNode());
        $this->assertSame($insert, $insert->returning->getParentNode());

        $values = new Values(new CtextRowList(array(array(new Constant(1)), array(new Constant(2)))));
        $insert->setValues($values);
        $this->assertSame($insert, $values->getParentNode());
        $insert->setValues(null);
        $this->assertNull($values->getParentNode());
    }

    public function testSelectStatement()
    {
        $select = new Select(
            new TargetList(array(
                new TargetElement(new ColumnReference(array('foo'))),
                new TargetElement(new ColumnReference(array('bar')))
            )),
            new ExpressionList(array(new ColumnReference(array('foo'))))
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
        $selectOne = new Select(new TargetList(array(new TargetElement(new Constant('foo')))));
        $selectTwo = new Select(new TargetList(array(new TargetElement(new Constant('bar')))));
        $setOp     = new SetOpSelect($selectOne, $selectTwo);

        $this->assertSame($setOp, $selectOne->getParentNode());
        $this->assertSame($setOp, $selectTwo->getParentNode());
    }

    public function testUpdateStatement()
    {
        $update = new Update(
            new RelationReference(new QualifiedName(array('foo', 'bar'))),
            new SetTargetList(array(new SetTargetElement(new Identifier('baz'), array(), new Constant('quux'))))
        );

        $this->assertSame($update, $update->relation->getParentNode());
        $this->assertSame($update, $update->set->getParentNode());
        $this->assertSame($update, $update->from->getParentNode());
        $this->assertSame($update, $update->where->getParentNode());
        $this->assertSame($update, $update->returning->getParentNode());
    }

    public function testValuesStatement()
    {
        $values = new Values(new CtextRowList(array(array(new Constant(1)), array(new Constant(2)))));

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
        $ref = new ColumnReference(array('foo', 'bar', 'baz', 'quux'));

        $this->assertSame($ref, $ref->catalog->getParentNode());
        $this->assertSame($ref, $ref->schema->getParentNode());
        $this->assertSame($ref, $ref->relation->getParentNode());
        $this->assertSame($ref, $ref->column->getParentNode());
    }

    public function testCommonTableExpression()
    {
        $cte = new CommonTableExpression(
            new Select(new TargetList(array(new TargetElement(new Constant('foo'))))),
            new Identifier('bar'),
            new IdentifierList(array('baz', 'quux'))
        );

        $this->assertSame($cte, $cte->statement->getParentNode());
        $this->assertSame($cte, $cte->alias->getParentNode());
        $this->assertSame($cte, $cte->columnAliases->getParentNode());
    }

    public function testFunctionCall()
    {
        $fn = new FunctionCall(
            new QualifiedName(array('foo', 'bar')),
            new FunctionArgumentList(array(new Constant(1)), false),
            false,
            new OrderByList(array(new OrderByElement(new ColumnReference(array('baz')))))
        );

        $this->assertSame($fn, $fn->name->getParentNode());
        $this->assertSame($fn, $fn->arguments->getParentNode());
        $this->assertSame($fn, $fn->order->getParentNode());
    }

    public function testIndirection()
    {
        $indirection = new Indirection(array(new Identifier('foo')), new Parameter('bar'));

        $this->assertSame($indirection, $indirection->expression->getParentNode());
    }

    public function testOrderByElement()
    {
        $order = new OrderByElement(new ColumnReference(array('foo')), 'asc', 'last');

        $this->assertSame($order, $order->expression->getParentNode());
    }

    public function testQualifiedName()
    {
        $name = new QualifiedName(array('foo', 'bar', 'baz'));

        $this->assertSame($name, $name->catalog->getParentNode());
        $this->assertSame($name, $name->schema->getParentNode());
        $this->assertSame($name, $name->relation->getParentNode());
    }

    public function testSetTargetElement()
    {
        $value  = new ColumnReference(array('foo', 'bar'));
        $target = new SetTargetElement(
            new Identifier('baz'),
            array(new Identifier('blah')),
            $value
        );

        $this->assertSame($target, $value->getParentNode());
        $this->assertSame($target, $target->name->getParentNode());

        $target->setValue(null);
        $this->assertNull($value->getParentNode());
    }

    public function testTargetElement()
    {
        $target = new TargetElement(
            new ColumnReference(array('foo', 'bar')),
            new Identifier('baz'),
            array(new Identifier('blah'))
        );

        $this->assertSame($target, $target->expression->getParentNode());
        $this->assertSame($target, $target->alias->getParentNode());
    }

    public function testTypeName()
    {
        $typename = new TypeName(
            new QualifiedName(array('foo', 'bar')),
            new TypeModifierList(array(new Constant(1)))
        );

        $this->assertSame($typename, $typename->name->getParentNode());
        $this->assertSame($typename, $typename->modifiers->getParentNode());

        $interval = new IntervalTypeName(new TypeModifierList(array(new Constant(1))));
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
            new Identifier('reference'), new ExpressionList(array(new ColumnReference(array('foo')))),
            new OrderByList(array(new OrderByElement(new ColumnReference(array('bar'))))), 'rows',
            $start, $end
        );

        $this->assertSame($window, $window->refName->getParentNode());
        $this->assertSame($window, $window->partition->getParentNode());
        $this->assertSame($window, $window->order->getParentNode());
        $this->assertSame($window, $window->frameStart->getParentNode());
        $this->assertSame($window, $window->frameEnd->getParentNode());

        $name = new Identifier('myname');
        $window->name = $name;
        $this->assertSame($window, $name->getParentNode());

        $window->setName(null);
        $this->assertNull($name->getParentNode());
    }

    public function testBetweenExpression()
    {
        $between = new BetweenExpression(new ColumnReference(array('foo')), new Constant(1), new Constant(2));

        $this->assertSame($between, $between->argument->getParentNode());
        $this->assertSame($between, $between->left->getParentNode());
        $this->assertSame($between, $between->right->getParentNode());
    }

    public function testCaseExpression()
    {
        $case = new CaseExpression(
            array(new WhenExpression(new ColumnReference(array('foo')), new Constant('foo'))),
            new Constant(666),
            new ColumnReference(array('bar'))
        );

        $this->assertSame($case, $case->argument->getParentNode());
        $this->assertSame($case, $case->else->getParentNode());

        $this->assertSame($case[0], $case[0]->when->getParentNode());
        $this->assertSame($case[0], $case[0]->then->getParentNode());
    }

    public function testCollateExpression()
    {
        $collate = new CollateExpression(new ColumnReference(array('foo')), new QualifiedName(array('bar', 'baz')));

        $this->assertSame($collate, $collate->argument->getParentNode());
        $this->assertSame($collate, $collate->collation->getParentNode());
    }

    public function testInExpression()
    {
        $in = new InExpression(
            new ColumnReference(array('foo')),
            new ExpressionList(array(new Constant('foo'), new Constant('bar')))
        );

        $this->assertSame($in, $in->left->getParentNode());
        $this->assertSame($in, $in->right->getParentNode());
    }

    public function testIsOfExpression()
    {
        $isOf = new IsOfExpression(
            new ColumnReference(array('foo')),
            new TypeList(array(new TypeName(new QualifiedName(array('pg_catalog', 'text')))))
        );

        $this->assertSame($isOf, $isOf->left->getParentNode());
        $this->assertSame($isOf, $isOf->right->getParentNode());
    }

    public function testOperatorExpression()
    {
        $operator = new OperatorExpression(
            '=', new ColumnReference(array('foo')), new Constant('foo')
        );

        $this->assertSame($operator, $operator->left->getParentNode());
        $this->assertSame($operator, $operator->right->getParentNode());
    }

    public function testPatternMatchingExpression()
    {
        $pattern = new PatternMatchingExpression(
            new ColumnReference(array('foo')), new Constant('blah%'), 'like', new Constant('!')
        );

        $this->assertSame($pattern, $pattern->argument->getParentNode());
        $this->assertSame($pattern, $pattern->pattern->getParentNode());
        $this->assertSame($pattern, $pattern->escape->getParentNode());
    }

    public function testSubselectExpression()
    {
        $subselect = new SubselectExpression(
            new Select(new TargetList(array(new TargetElement(new ColumnReference(array('foo'))))))
        );

        $this->assertSame($subselect, $subselect->query->getParentNode());
    }

    public function testTypecastExpression()
    {
        $typecast = new TypecastExpression(
            new ColumnReference(array('foo')), new TypeName(new QualifiedName(array('bar', 'baz')))
        );

        $this->assertSame($typecast, $typecast->argument->getParentNode());
        $this->assertSame($typecast, $typecast->type->getParentNode());
    }

    public function testColumnDefinition()
    {
        $colDef = new ColumnDefinition(
            new Identifier('blah'),
            new TypeName(new QualifiedName(array('foo', 'bar'))),
            new QualifiedName(array('weirdcollation'))
        );

        $this->assertSame($colDef, $colDef->name->getParentNode());
        $this->assertSame($colDef, $colDef->type->getParentNode());
        $this->assertSame($colDef, $colDef->collation->getParentNode());
    }

    public function testRelationReference()
    {
        $ref = new RelationReference(new QualifiedName(array('foo', 'bar')));

        $this->assertSame($ref, $ref->name->getParentNode());

        $tableAlias    = new Identifier('blah');
        $columnAliases = new IdentifierList(array('baz', 'quux'));

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
            new QualifiedName(array('foo', 'bar')),
            new FunctionArgumentList(array(new Constant(1)))
        ));

        $this->assertSame($fn, $fn->function->getParentNode());

        $tableAlias    = new Identifier('blah');
        $columnAliases = new ColumnDefinitionList(array(
            new ColumnDefinition(
                new Identifier('blahtext'),
                new TypeName(new QualifiedName(array('pg_catalog', 'text')))
            )
        ));

        $fn->setAlias($tableAlias, $columnAliases);
        $this->assertSame($fn, $tableAlias->getParentNode());
        $this->assertSame($fn, $columnAliases->getParentNode());
    }

    public function testJoinExpression()
    {
        $join = new JoinExpression(
            new RelationReference(new QualifiedName(array('foo', 'bar'))),
            new RelationReference(new QualifiedName(array('baz', 'quux')))
        );

        $this->assertSame($join, $join->left->getParentNode());
        $this->assertSame($join, $join->right->getParentNode());

        $using = new IdentifierList(array('one', 'two', 'three'));
        $join->setUsing($using);
        $this->assertSame($join, $using->getParentNode());

        $join->using = null;
        $this->assertNull($using->getParentNode());
        $join->setOn(new OperatorExpression('=', new ColumnReference(array('one')), new ColumnReference(array('two'))));
        $this->assertSame($join, $join->on->getParentNode());
    }

    public function testRangeSubselect()
    {
        $subselect = new Subselect(
            new Select(new TargetList(array(new TargetElement(new Constant('foo')))))
        );

        $this->assertSame($subselect, $subselect->query->getParentNode());
    }

    public function testXmlElement()
    {
        $xml = new XmlElement(
            new Identifier('name'),
            new TargetList(array(new TargetElement(new Constant('attvalue'), new Identifier('attname')))),
            new ExpressionList(array(new Constant('stuff')))
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
        $xml = new XmlRoot(new ColumnReference(array('doc')), new Constant('1.2'), 'yes');

        $this->assertSame($xml, $xml->xml->getParentNode());
        $this->assertSame($xml, $xml->version->getParentNode());
    }

    public function testXmlSerialize()
    {
        $xml = new XmlSerialize(
            'document',
            new ColumnReference(array('foo')),
            new TypeName(new QualifiedName(array('pg_catalog', 'text')))
        );

        $this->assertSame($xml, $xml->argument->getParentNode());
        $this->assertSame($xml, $xml->type->getParentNode());
    }
}
