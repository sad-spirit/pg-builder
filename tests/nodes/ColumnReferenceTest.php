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
 * @copyright 2014-2022 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\tests\nodes;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\exceptions\{
    InvalidArgumentException,
    SyntaxException
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    Identifier,
    Star
};

class ColumnReferenceTest extends TestCase
{
    /**
     * @dataProvider validColumnReferencesProvider
     * @param array $arguments
     * @param array $expected
     */
    public function testCreateValidColumnReferences(array $arguments, array $expected): void
    {
        $reference = new ColumnReference(...$arguments);
        $this::assertEquals(
            $expected,
            [
                null === $reference->catalog  ? null : clone $reference->catalog,
                null === $reference->schema   ? null : clone $reference->schema,
                null === $reference->relation ? null : clone $reference->relation,
                clone $reference->column
            ]
        );
    }

    /**
     * @dataProvider invalidColumnReferencesProvider
     * @param array                    $arguments
     * @param class-string<\Throwable> $class
     * @param string                   $message
     */
    public function testCreateInvalidColumnReferences(array $arguments, string $class, string $message): void
    {
        $this::expectException($class);
        $this::expectExceptionMessage($message);
        new ColumnReference(...$arguments);
    }

    public function validColumnReferencesProvider(): array
    {
        return [
            [
                ['foo'],
                [null, null, null, new Identifier('foo')]
            ],
            [
                ['foo', 'bar'],
                [null, null, new Identifier('foo'), new Identifier('bar')]
            ],
            [
                ['foo', 'bar', 'baz'],
                [null, new Identifier('foo'), new Identifier('bar'), new Identifier('baz')]
            ],
            [
                ['foo', 'bar', 'baz', 'quux'],
                [new Identifier('foo'), new Identifier('bar'), new Identifier('baz'), new Identifier('quux')]
            ],
            [
                ['foo', '*'],
                [null, null, new Identifier('foo'), new Star()]
            ],
            [
                ['*', 'foo'],
                [null, null, new Identifier('*'), new Identifier('foo')]
            ],
            [
                [new Identifier('foo'), new Identifier('*')],
                [null, null, new Identifier('foo'), new Identifier('*')]
            ],
        ];
    }

    public function invalidColumnReferencesProvider(): array
    {
        $foo = new Identifier('foo');

        return [
            [
                [],
                InvalidArgumentException::class,
                'at least one name part'
            ],
            [
                ['foo', 'bar', 'baz', 'quux', 'xyzzy'],
                SyntaxException::class,
                'Too many dots'
            ],
            [
                [new Star(), new Identifier('foo')],
                InvalidArgumentException::class,
                'could not be converted to Identifier'
            ],
            [
                ['first', 1],
                InvalidArgumentException::class,
                'could not be converted to Identifier'
            ],
            [
                [$foo, $foo],
                InvalidArgumentException::class,
                'Cannot use the same Node'
            ]
        ];
    }
}
