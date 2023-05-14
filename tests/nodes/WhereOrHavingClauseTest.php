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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\tests\nodes;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\{
    Lexer,
    Parser,
    Select
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    WhereOrHavingClause,
    expressions\KeywordConstant,
    expressions\LogicalExpression
};

/**
 * Tests helper methods for WhereOrHavingClause
 */
class WhereOrHavingClauseTest extends TestCase
{
    public function testConditionIsNullByDefault(): void
    {
        $this::assertNull((new WhereOrHavingClause())->condition);
    }

    public function testAddConditionsWithAnd(): void
    {
        $where = new WhereOrHavingClause(new ColumnReference('foo'));
        $where->and(new ColumnReference('bar'))
            ->and(new LogicalExpression([
                new ColumnReference('baz'),
                new ColumnReference('quux')
            ], 'and'));
        $this->assertEquals(
            new LogicalExpression([
                new ColumnReference('foo'),
                new ColumnReference('bar'),
                new ColumnReference('baz'),
                new ColumnReference('quux')
            ], 'and'),
            clone $where->condition
        );
    }

    public function testAddConditionsWithOr(): void
    {
        $where = new WhereOrHavingClause(new ColumnReference('foo'));
        $where->or(new ColumnReference('bar'))
            ->or(new LogicalExpression([
                new ColumnReference('baz'),
                new ColumnReference('quux')
            ], 'or'));
        $this->assertEquals(
            new LogicalExpression([
                new ColumnReference('foo'),
                new ColumnReference('bar'),
                new ColumnReference('baz'),
                new ColumnReference('quux')
            ], 'or'),
            clone $where->condition
        );
    }

    public function testAddConditionsWithAndAndOr(): void
    {
        $where = new WhereOrHavingClause(new ColumnReference('foo'));
        $where->and(new ColumnReference('bar'))
            ->or(new ColumnReference('baz'));

        $this->assertEquals(
            new LogicalExpression(
                [
                    new LogicalExpression(
                        [
                            new ColumnReference('foo'),
                            new ColumnReference('bar')
                        ],
                        'and'
                    ),
                    new ColumnReference('baz')
                ],
                'or'
            ),
            clone $where->condition
        );
    }

    public function testAddNestedConditions(): void
    {
        $where = new WhereOrHavingClause(new ColumnReference('foo'));
        $where->and(
            $where->nested(new ColumnReference('bar'))
                ->or(new ColumnReference('baz'))
        );

        $this->assertEquals(
            new LogicalExpression(
                [
                    new ColumnReference('foo'),
                    new LogicalExpression(
                        [
                            new ColumnReference('bar'),
                            new ColumnReference('baz')
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
     * @noinspection SqlNoDataSourceInspection
     * @noinspection SqlResolve
     */
    public function testNestedWhereShouldHaveParser(): void
    {
        $parser = new Parser(new Lexer());
        /** @var Select $select */
        $select = $parser->parseSelectStatement("select * from foo where bar = 'baz'");
        $select->setParser($parser);

        $select->where->and(
            $select->where->nested("some_field > 'some value'")->or("other_field < 'other value'")
        );
    }

    public function testBugWhenFirstConditionIsNested(): void
    {
        $where = new WhereOrHavingClause();
        $where->and(
            $where->nested(new ColumnReference('foo'))
                ->or(new ColumnReference('bar'))
        );
        $where->and(new ColumnReference('baz'));

        $this->assertEquals(
            new LogicalExpression(
                [
                    new LogicalExpression(
                        [
                            new ColumnReference('foo'),
                            new ColumnReference('bar')
                        ],
                        'or'
                    ),
                    new ColumnReference('baz')
                ],
                'and'
            ),
            clone $where->condition
        );
    }

    public function testExplicitOrExpressionsShouldBeAutoNestedInAnd(): void
    {
        $where = new WhereOrHavingClause();
        $where->and(new ColumnReference('foo'));
        $where->and(new LogicalExpression(
            [
                new ColumnReference('bar'),
                new ColumnReference('baz'),
            ],
            'or'
        ));

        $this->assertEquals(
            new LogicalExpression(
                [
                    new ColumnReference('foo'),
                    new LogicalExpression(
                        [
                            new ColumnReference('bar'),
                            new ColumnReference('baz'),
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
                new ColumnReference('bar'),
                new ColumnReference('baz'),
            ],
            'or'
        ));
        $where->and(new ColumnReference('foo'));

        $this->assertEquals(
            new LogicalExpression(
                [
                    new LogicalExpression(
                        [
                            new ColumnReference('bar'),
                            new ColumnReference('baz'),
                        ],
                        'or'
                    ),
                    new ColumnReference('foo')
                ],
                'and'
            ),
            clone $where->condition
        );
    }

    public function testAddEmptyWhereClauseWithAndAndOrBug(): void
    {
        $true  = new KeywordConstant(KeywordConstant::TRUE);
        $whereEmpty = new WhereOrHavingClause();
        $whereTrue  = new WhereOrHavingClause($true);

        $whereEmpty->and(new WhereOrHavingClause())->or(new WhereOrHavingClause());
        $whereTrue->and(new WhereOrHavingClause())->or(new WhereOrHavingClause());

        $this::assertEquals(new WhereOrHavingClause(), $whereEmpty);
        $this::assertEquals(new WhereOrHavingClause(clone $true), $whereTrue);
    }

    public function testEmptyLogicalExpressionBug(): void
    {
        $where = new WhereOrHavingClause(new LogicalExpression([], LogicalExpression::OR));
        $where->and(new KeywordConstant(KeywordConstant::TRUE));

        $this::assertEquals(
            new LogicalExpression([new KeywordConstant(KeywordConstant::TRUE)], LogicalExpression::OR),
            clone $where->condition
        );
    }
}
