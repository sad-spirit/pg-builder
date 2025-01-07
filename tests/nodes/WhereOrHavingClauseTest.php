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
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\tests\nodes;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\{
    Lexer,
    Parser,
    Select,
    enums\ConstantName,
    enums\LogicalOperator
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
            ], LogicalOperator::AND));
        $this->assertEquals(
            new LogicalExpression([
                new ColumnReference('foo'),
                new ColumnReference('bar'),
                new ColumnReference('baz'),
                new ColumnReference('quux')
            ], LogicalOperator::AND),
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
            ], LogicalOperator::OR));
        $this->assertEquals(
            new LogicalExpression([
                new ColumnReference('foo'),
                new ColumnReference('bar'),
                new ColumnReference('baz'),
                new ColumnReference('quux')
            ], LogicalOperator::OR),
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
                        LogicalOperator::AND
                    ),
                    new ColumnReference('baz')
                ],
                LogicalOperator::OR
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
                        LogicalOperator::OR
                    )
                ],
                LogicalOperator::AND
            ),
            clone $where->condition
        );
    }

    /**
     * @noinspection SqlNoDataSourceInspection
     * @noinspection SqlResolve
     */
    #[DoesNotPerformAssertions]
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
                        LogicalOperator::OR
                    ),
                    new ColumnReference('baz')
                ],
                LogicalOperator::AND
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
            LogicalOperator::OR
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
                        LogicalOperator::OR
                    )
                ],
                LogicalOperator::AND
            ),
            clone $where->condition
        );

        $where = new WhereOrHavingClause();
        $where->and(new LogicalExpression(
            [
                new ColumnReference('bar'),
                new ColumnReference('baz'),
            ],
            LogicalOperator::OR
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
                        LogicalOperator::OR
                    ),
                    new ColumnReference('foo')
                ],
                LogicalOperator::AND
            ),
            clone $where->condition
        );
    }

    public function testAddEmptyWhereClauseWithAndAndOrBug(): void
    {
        $true  = new KeywordConstant(ConstantName::TRUE);
        $whereEmpty = new WhereOrHavingClause();
        $whereTrue  = new WhereOrHavingClause($true);

        $whereEmpty->and(new WhereOrHavingClause())->or(new WhereOrHavingClause());
        $whereTrue->and(new WhereOrHavingClause())->or(new WhereOrHavingClause());

        $this::assertEquals(new WhereOrHavingClause(), $whereEmpty);
        $this::assertEquals(new WhereOrHavingClause(clone $true), $whereTrue);
    }

    public function testEmptyLogicalExpressionBug(): void
    {
        $where = new WhereOrHavingClause(new LogicalExpression([], LogicalOperator::OR));
        $where->and(new KeywordConstant(ConstantName::TRUE));

        $this::assertEquals(
            new LogicalExpression([new KeywordConstant(ConstantName::TRUE)], LogicalOperator::OR),
            clone $where->condition
        );
    }
}
