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

namespace sad_spirit\pg_builder\tests\nodes;

use sad_spirit\pg_builder\Lexer,
    sad_spirit\pg_builder\Parser,
    sad_spirit\pg_builder\nodes\ColumnReference,
    sad_spirit\pg_builder\nodes\WhereOrHavingClause,
    sad_spirit\pg_builder\nodes\expressions\LogicalExpression;

/**
 * Tests helper methods for WhereOrHavingClause
 */
class WhereOrHavingClauseTest extends \PHPUnit\Framework\TestCase
{
    public function testAddConditionsWithAnd()
    {
        $where = new WhereOrHavingClause(new ColumnReference(array('foo')));
        $where->and_(new ColumnReference(array('bar')))
            ->and_(new LogicalExpression(array(
                new ColumnReference(array('baz')),
                new ColumnReference(array('quux'))
            ), 'and'));
        $this->assertEquals(
            new LogicalExpression(array(
                new ColumnReference(array('foo')),
                new ColumnReference(array('bar')),
                new ColumnReference(array('baz')),
                new ColumnReference(array('quux'))
            ), 'and'),
            clone $where->condition
        );
    }

    public function testAddConditionsWithOr()
    {
        $where = new WhereOrHavingClause(new ColumnReference(array('foo')));
        $where->or_(new ColumnReference(array('bar')))
            ->or_(new LogicalExpression(array(
                new ColumnReference(array('baz')),
                new ColumnReference(array('quux'))
            ), 'or'));
        $this->assertEquals(
            new LogicalExpression(array(
                new ColumnReference(array('foo')),
                new ColumnReference(array('bar')),
                new ColumnReference(array('baz')),
                new ColumnReference(array('quux'))
            ), 'or'),
            clone $where->condition
        );
    }

    public function testAddConditionsWithAndAndOr()
    {
        $where = new WhereOrHavingClause(new ColumnReference(array('foo')));
        $where->and_(new ColumnReference(array('bar')))
            ->or_(new ColumnReference(array('baz')));

        $this->assertEquals(
            new LogicalExpression(
                array(
                    new LogicalExpression(
                        array(
                            new ColumnReference(array('foo')),
                            new ColumnReference(array('bar'))
                        ),
                        'and'
                    ),
                    new ColumnReference(array('baz'))
                ),
                'or'
            ),
            clone $where->condition
        );
    }

    public function testAddNestedConditions()
    {
        $where = new WhereOrHavingClause(new ColumnReference(array('foo')));
        $where->and_(
            $where->nested(new ColumnReference(array('bar')))
                ->or_(new ColumnReference(array('baz'))
        ));

        $this->assertEquals(
            new LogicalExpression(
                array(
                    new ColumnReference(array('foo')),
                    new LogicalExpression(
                        array(
                            new ColumnReference(array('bar')),
                            new ColumnReference(array('baz'))
                        ),
                        'or'
                    )
                ),
                'and'
            ),
            clone $where->condition
        );
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testNestedWhereShouldHaveParser()
    {
        $parser = new Parser(new Lexer());
        /* @var $select \sad_spirit\pg_builder\Select */
        $select = $parser->parseSelectStatement("select * from foo where bar = 'baz'");
        $select->setParser($parser);

        $select->where->and_(
            $select->where->nested("some_field > 'some value'")->or_("other_field < 'other value'")
        );
    }

    public function testBugWhenFirstConditionIsNested()
    {
        $where = new WhereOrHavingClause();
        $where->and_(
            $where->nested(new ColumnReference(array('foo')))
                ->or_(new ColumnReference(array('bar'))
        ));
        $where->and_(new ColumnReference(array('baz')));

        $this->assertEquals(
            new LogicalExpression(
                array(
                    new LogicalExpression(
                        array(
                            new ColumnReference(array('foo')),
                            new ColumnReference(array('bar'))
                        ),
                        'or'
                    ),
                    new ColumnReference(array('baz'))
                ),
                'and'
            ),
            clone $where->condition
        );
    }

    public function testExplicitOrExpressionsShouldBeAutoNestedInAnd()
    {
        $where = new WhereOrHavingClause();
        $where->and_(new ColumnReference(array('foo')));
        $where->and_(new LogicalExpression(
            array(
                new ColumnReference(array('bar')),
                new ColumnReference(array('baz')),
            ),
            'or'
        ));

        $this->assertEquals(
            new LogicalExpression(
                array(
                    new ColumnReference(array('foo')),
                    new LogicalExpression(
                        array(
                            new ColumnReference(array('bar')),
                            new ColumnReference(array('baz')),
                        ),
                        'or'
                    )
                ),
                'and'
            ),
            clone $where->condition
        );

        $where = new WhereOrHavingClause();
        $where->and_(new LogicalExpression(
            array(
                new ColumnReference(array('bar')),
                new ColumnReference(array('baz')),
            ),
            'or'
        ));
        $where->and_(new ColumnReference(array('foo')));

        $this->assertEquals(
            new LogicalExpression(
                array(
                    new LogicalExpression(
                        array(
                            new ColumnReference(array('bar')),
                            new ColumnReference(array('baz')),
                        ),
                        'or'
                    ),
                    new ColumnReference(array('foo'))
                ),
                'and'
            ),
            clone $where->condition
        );
    }
}