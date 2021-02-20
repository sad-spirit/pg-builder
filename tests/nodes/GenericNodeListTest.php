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
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\Star;

/**
 * Tests the functionality implemented in GenericNodeList
 */
class GenericNodeListTest extends TestCase
{
    /** @var GenericNodeListImplementation */
    private $nodeList;
    /** @var mixed */
    private $error;

    protected function setUp(): void
    {
        $this->nodeList = new GenericNodeListImplementation();
        $this->error    = null;
    }

    public function testCannotReadUndefinedOffset(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Undefined offset');
        $this->error = $this->nodeList[1];
    }

    public function testAcceptsAnyNodeInstance(): void
    {
        $star = new Star();
        $impl = new GenericNodeImplementation();

        $this->nodeList[3] = $star;
        $this->nodeList[5] = $impl;

        $this::assertCount(2, $this->nodeList);
        $this::assertSame($this->nodeList, $star->getParentNode());
        $this::assertSame($this->nodeList, $impl->getParentNode());
    }

    /**
     * @dataProvider invalidValuesProvider
     * @param mixed $invalidValue
     */
    public function testAcceptsOnlyNodeInstances($invalidValue): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('can contain only instances of Node');
        $this->nodeList[] = $invalidValue;
    }

    public function invalidValuesProvider(): array
    {
        return [
            [null],
            [false],
            ['a string'],
            [666],
            [[]],
            [new \stdClass()]
        ];
    }

    public function testUnsetRemovesParentNode(): void
    {
        $impl = new GenericNodeImplementation();
        $this->nodeList[666] = $impl;

        $this::assertTrue(isset($this->nodeList[666]));
        unset($this->nodeList[666]);
        $this::assertFalse(isset($this->nodeList[666]));
        $this::assertNull($impl->getParentNode());
    }

    public function testReplaceChildHandlesArrayValues(): void
    {
        $childOne = new GenericNodeListImplementation();
        $childTwo = new GenericNodeListImplementation();

        $this->nodeList['one'] = $childOne;
        $this->nodeList['two'] = $childTwo;
        $this->nodeList->replaceChild($childOne, $childTwo);

        $this::assertFalse(isset($this->nodeList['two']));
        $this::assertNull($childOne->getParentNode());
        $this::assertSame($childTwo, $this->nodeList['one']);
    }

    public function testRemoveChildHandlesArrayValues(): void
    {
        $child = new GenericNodeImplementation();

        $this->nodeList['child'] = $child;
        $this->nodeList->removeChild($child);

        $this::assertFalse(isset($this->nodeList['child']));
        $this::assertNull($child->getParentNode());
    }

    public function testClonePerformsDeepCloneOfArrayValues(): void
    {
        $genericNode = new GenericNodeImplementation();
        $this->nodeList[] = $genericNode;

        $cloned = clone $this->nodeList;
        $this::assertInstanceOf(GenericNodeImplementation::class, $cloned[0]);
        $this::assertNotSame($genericNode, $cloned[0]);
    }

    public function testUnserializeRestoresParentChildRelationshipsOfArrayValues(): void
    {
        $this->nodeList[] = new GenericNodeImplementation();

        /* @var GenericNodeListImplementation $unserialized */
        $unserialized = unserialize(serialize($this->nodeList));

        $this::assertInstanceOf(GenericNodeImplementation::class, $unserialized[0]);
        $this::assertSame($unserialized, $unserialized[0]->getParentNode());
    }
}
