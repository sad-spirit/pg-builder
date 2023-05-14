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

namespace sad_spirit\pg_builder\tests\nodes\expressions;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    IntervalTypeName,
    lists\TypeModifierList,
    QualifiedName,
    ScalarExpression,
    TypeName,
    expressions\ConstantTypecastExpression,
    expressions\NumericConstant,
    expressions\StringConstant
};
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\SqlBuilderWalker;

/**
 * Additional checks that ConstantTypecastExpression only accepts arguments suitable for constant expressions
 */
class ConstantTypecastExpressionTest extends TestCase
{
    /**
     * @dataProvider invalidArgumentProvider
     * @param ScalarExpression $argument
     */
    public function testInvalidArgumentViaConstructor(ScalarExpression $argument): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('generic string constant');
        new ConstantTypecastExpression($argument, new TypeName(new QualifiedName('foo')));
    }

    /**
     * @dataProvider invalidArgumentProvider
     * @param ScalarExpression $argument
     * @psalm-suppress PropertyTypeCoercion
     */
    public function testInvalidArgumentViaSetter(ScalarExpression $argument): void
    {
        $typecast = new ConstantTypecastExpression(new StringConstant(''), new TypeName(new QualifiedName('foo')));

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('generic string constant');
        /** @phpstan-ignore-next-line */
        $typecast->argument = $argument;
    }

    public function testDisallowTypeNameWithArrayBoundsViaConstructor(): void
    {
        $typename = new TypeName(new QualifiedName('foo'));
        $typename->bounds = [10];

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('cannot be used in constant type cast');
        new ConstantTypecastExpression(new StringConstant('bar'), $typename);
    }

    public function testDisallowSettingArrayBoundsOnTypeName(): void
    {
        $typecast = new ConstantTypecastExpression(new StringConstant(''), new TypeName(new QualifiedName('foo')));

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('cannot be used in constant type cast');
        $typecast->type->bounds = [10];
    }

    public function testDisallowTypeNameWithSetOfViaConstructor(): void
    {
        $typename = new TypeName(new QualifiedName('foo'));
        $typename->setOf = true;

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('cannot be used in constant type cast');
        new ConstantTypecastExpression(new StringConstant(''), $typename);
    }

    public function testDisallowEnablingSetOfOnTypeName(): void
    {
        $typecast = new ConstantTypecastExpression(new StringConstant(''), new TypeName(new QualifiedName('foo')));

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('cannot be used in constant type cast');
        $typecast->type->setOf = true;
    }

    /**
     * @dataProvider sqlProvider
     * @param TypeName $type
     * @param string $sql
     */
    public function testSqlGeneration(TypeName $type, string $sql): void
    {
        $typecast = new ConstantTypecastExpression(new StringConstant('bar'), $type);
        $builder  = new SqlBuilderWalker();

        $this::assertEquals($sql, $typecast->dispatch($builder));
    }

    public function invalidArgumentProvider(): array
    {
        return [
            [new ColumnReference('foo', 'bar')],
            [new NumericConstant('1.2')],
            [new StringConstant('1010', StringConstant::TYPE_BINARY)]
        ];
    }

    public function sqlProvider(): array
    {
        $masked = new IntervalTypeName();
        $masked->mask = 'hour to second';
        $maskedPrecision = new IntervalTypeName(new TypeModifierList([new NumericConstant('1')]));
        $maskedPrecision->mask = 'day to second';

        return [
            [
                new TypeName(new QualifiedName('foo')),
                "foo 'bar'"
            ],
            [
                new TypeName(
                    new QualifiedName('foo'),
                    new TypeModifierList([new NumericConstant('1')])
                ),
                "foo (1) 'bar'"
            ],
            [
                new IntervalTypeName(new TypeModifierList([new NumericConstant('1')])),
                "interval (1) 'bar'"
            ],
            [
                $masked,
                "interval 'bar' hour to second"
            ],
            [
                $maskedPrecision,
                "interval 'bar' day to second (1)"
            ]
        ];
    }
}
