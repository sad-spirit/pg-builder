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

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\{
    exceptions\InvalidArgumentException,
    Lexer,
    Parser
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    expressions\OperatorExpression,
    SetToDefault
};

/**
 * Since almost all lists in the package are based on NonAssociativeList, let's test that
 */
class NonAssociativeListTest extends TestCase
{
    /**
     * @dataProvider invalidOffsetsProvider
     * @param mixed $offset
     */
    public function testDisallowsNonIntegerAndNegativeOffsets($offset): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Non-negative integer offsets expected');
        $nodeList = new NonAssociativeListImplementation();
        $nodeList[$offset] = new SetToDefault();
    }

    public function invalidOffsetsProvider(): array
    {
        return [
            [-1],
            ['foo'],
            ['2much']
        ];
    }

    /**
     * @psalm-suppress InvalidArgument
     */
    public function testAllowsOnlyClassesReturnedByGetAllowedElementClasses(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('ScalarExpression or SetToDefault');

        $nodeList   = new NonAssociativeListImplementation();
        // @phpstan-ignore-next-line
        $nodeList[] = new GenericNodeImplementation();
    }

    /**
     * @psalm-suppress InvalidArgument
     */
    public function testReplaceAllowsOnlyClassesReturnedByGetAllowedElementClasses(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('ScalarExpression or SetToDefault');

        // @phpstan-ignore-next-line
        new NonAssociativeListImplementation([new GenericNodeImplementation()]);
    }

    /**
     * @psalm-suppress InvalidArgument
     */
    public function testMergeAllowsOnlyClassesReturnedByGetAllowedElementClasses(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('ScalarExpression or SetToDefault');

        $nodeList = new NonAssociativeListImplementation();
        // @phpstan-ignore-next-line
        $nodeList->merge([new GenericNodeImplementation()]);
    }

    public function testCanPassStringsWhenParserIsAvailable(): void
    {
        $nodeList = new NonAssociativeListImplementation(
            'a + b',
            new Parser(new Lexer())
        );
        $nodeList[] = 'default';
        $nodeList->merge('c - d, e');

        $this::assertEquals(
            [
                new OperatorExpression('+', new ColumnReference('a'), new ColumnReference('b')),
                new SetToDefault(),
                new OperatorExpression('-', new ColumnReference('c'), new ColumnReference('d')),
                new ColumnReference('e')
            ],
            unserialize(serialize(iterator_to_array($nodeList)))
        );
    }

    public function testReplaceIgnoresArrayKeys(): void
    {
        $nodeOne   = new OperatorExpression('+', new ColumnReference('a'), new ColumnReference('b'));
        $nodeTwo   = new OperatorExpression('-', new ColumnReference('c'), new ColumnReference('d'));
        $nodeThree = new SetToDefault();

        $nodeList  = new NonAssociativeListImplementation([$nodeOne]);
        $this::assertSame($nodeList, $nodeOne->getParentNode());

        $nodeList->replace([666 => $nodeTwo, 'foobar' => $nodeThree]);
        $this::assertNull($nodeOne->getParentNode());
        $this::assertEquals(
            [$nodeTwo, $nodeThree],
            iterator_to_array($nodeList)
        );
    }

    public function testMergeIgnoresArrayKeys(): void
    {
        $nodeOne   = new OperatorExpression('+', new ColumnReference('a'), new ColumnReference('b'));
        $nodeTwo   = new OperatorExpression('-', new ColumnReference('c'), new ColumnReference('d'));
        $nodeThree = new SetToDefault();

        $nodeList  = new NonAssociativeListImplementation();
        $nodeList[666] = $nodeOne;
        $nodeList->merge(['a' => $nodeTwo], ['a' => $nodeThree]);

        $this::assertEquals(
            [$nodeOne, $nodeTwo, $nodeThree],
            iterator_to_array($nodeList)
        );
    }
}
