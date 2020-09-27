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

use sad_spirit\pg_builder\Lexer;
use sad_spirit\pg_builder\Parser;
use sad_spirit\pg_builder\nodes\ColumnReference;
use sad_spirit\pg_builder\nodes\WhereOrHavingClause;
use sad_spirit\pg_builder\nodes\expressions\LogicalExpression;

/**
 * Tests helper methods for WhereOrHavingClause
 */
class WhereOrHavingClauseTest extends \PHPUnit\Framework\TestCase
{
    public function testAddConditionsWithAnd()
    {
        $where = new WhereOrHavingClause(new ColumnReference(['foo']));
        $where->and(new ColumnReference(['bar']))
            ->and(new LogicalExpression([
                new ColumnReference(['baz']),
                new ColumnReference(['quux'])
            ], 'and'));
        $this->assertEquals(
            new LogicalExpression([
                new ColumnReference(['foo']),
                new ColumnReference(['bar']),
                new ColumnReference(['baz']),
                new ColumnReference(['quux'])
            ], 'and'),
            clone $where->condition
        );
    }

    public function testAddConditionsWithOr()
    {
        $where = new WhereOrHavingClause(new ColumnReference(['foo']));
        $where->or(new ColumnReference(['bar']))
            ->or(new LogicalExpression([
                new ColumnReference(['baz']),
                new ColumnReference(['quux'])
            ], 'or'));
        $this->assertEquals(
            new LogicalExpression([
                new ColumnReference(['foo']),
                new ColumnReference(['bar']),
                new ColumnReference(['baz']),
                new ColumnReference(['quux'])
            ], 'or'),
            clone $where->condition
        );
    }

    public function testAddConditionsWithAndAndOr()
    {
        $where = new WhereOrHavingClause(new ColumnReference(['foo']));
        $where->and(new ColumnReference(['bar']))
            ->or(new ColumnReference(['baz']));

        $this->assertEquals(
            new LogicalExpression(
                [
                    new LogicalExpression(
                        [
                            new ColumnReference(['foo']),
                            new ColumnReference(['bar'])
                        ],
                        'and'
                    ),
                    new ColumnReference(['baz'])
                ],
                'or'
            ),
            clone $where->condition
        );
    }

    public function testAddNestedConditions()
    {
        $where = new WhereOrHavingClause(new ColumnReference(['foo']));
        $where->and(
            $where->nested(new ColumnReference(['bar']))
                ->or(new ColumnReference(['baz']))
        );

        $this->assertEquals(
            new LogicalExpression(
                [
                    new ColumnReference(['foo']),
                    new LogicalExpression(
                        [
                            new ColumnReference(['bar']),
                            new ColumnReference(['baz'])
                        ],
                        'or'
                    )
                ],
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

        $select->where->and(
            $select->where->nested("some_field > 'some value'")->or("other_field < 'other value'")
        );
    }

    public function testBugWhenFirstConditionIsNested()
    {
        $where = new WhereOrHavingClause();
        $where->and(
            $where->nested(new ColumnReference(['foo']))
                ->or(new ColumnReference(['bar']))
        );
        $where->and(new ColumnReference(['baz']));

        $this->assertEquals(
            new LogicalExpression(
                [
                    new LogicalExpression(
                        [
                            new ColumnReference(['foo']),
                            new ColumnReference(['bar'])
                        ],
                        'or'
                    ),
                    new ColumnReference(['baz'])
                ],
                'and'
            ),
            clone $where->condition
        );
    }

    public function testExplicitOrExpressionsShouldBeAutoNestedInAnd()
    {
        $where = new WhereOrHavingClause();
        $where->and(new ColumnReference(['foo']));
        $where->and(new LogicalExpression(
            [
                new ColumnReference(['bar']),
                new ColumnReference(['baz']),
            ],
            'or'
        ));

        $this->assertEquals(
            new LogicalExpression(
                [
                    new ColumnReference(['foo']),
                    new LogicalExpression(
                        [
                            new ColumnReference(['bar']),
                            new ColumnReference(['baz']),
                        ],
                        'or'
                    )
                ],
                'and'
            ),
            clone $where->condition
        );

        $where = new WhereOrHavingClause();
        $where->and(new LogicalExpression(
            [
                new ColumnReference(['bar']),
                new ColumnReference(['baz']),
            ],
            'or'
        ));
        $where->and(new ColumnReference(['foo']));

        $this->assertEquals(
            new LogicalExpression(
                [
                    new LogicalExpression(
                        [
                            new ColumnReference(['bar']),
                            new ColumnReference(['baz']),
                        ],
                        'or'
                    ),
                    new ColumnReference(['foo'])
                ],
                'and'
            ),
            clone $where->condition
        );
    }
}
