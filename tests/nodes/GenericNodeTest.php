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
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\Node;

/**
 * Tests the functionality implemented in GenericNode
 */
class GenericNodeTest extends TestCase
{
    /** @var GenericNodeImplementation */
    private $node;
    private $error;

    protected function setUp(): void
    {
        $this->node  = new GenericNodeImplementation(new GenericNodeImplementation());
        $this->error = null;
    }

    public function testCanReadDefinedProperty()
    {
        $this::assertNull($this->node->child);
        $this::assertInstanceOf(Node::class, $this->node->readonly);
    }

    public function testCannotReadUndefinedProperty()
    {
        $this::assertFalse(isset($this->node->foo));

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Unknown property');

        $this->error = $this->node->foo;
    }

    public function testCannotWriteUndefinedProperty()
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Unknown property');

        $this->node->foo = 'bar';
    }

    public function testWritingPropertyCallsExplicitSetter()
    {
        $this::assertEquals(0, $this->node->setChildCalled);

        $newChild          = new GenericNodeImplementation();
        $this->node->child = $newChild;

        $this::assertSame($newChild, $this->node->child);
        $this::assertEquals(1, $this->node->setChildCalled);
    }

    public function testRemoveChildCallsExplicitSetter()
    {
        $newChild = new GenericNodeImplementation();
        $this->node->child = $newChild;

        $this->node->removeChild($newChild);
        $this::assertNull($this->node->child);
        $this::assertEquals(2, $this->node->setChildCalled);
    }

    public function testReplaceChildCallsExplicitSetter()
    {
        $newChild    = new GenericNodeImplementation();
        $newestChild = new GenericNodeImplementation();

        $this->node->child = $newChild;

        $this->node->replaceChild($newChild, $newestChild);
        $this::assertSame($newestChild, $this->node->child);
        $this::assertEquals(2, $this->node->setChildCalled);
    }

    public function testCannotWritePropertyWithoutExplicitSetter()
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('is read-only');

        $this->node->readonly = new GenericNodeImplementation();
    }

    public function testCannotReplaceChildWithoutExplicitSetter()
    {
        $readonly = $this->node->readonly;
        $replace  = new GenericNodeImplementation();

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('is read-only');

        $this->node->replaceChild($readonly, $replace);
    }

    public function testCannotRemoveChildWithoutExplicitSetter()
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('is read-only');

        $this->node->removeChild($this->node->readonly);
    }

    public function testCannotRemoveNonChild()
    {
        $nonChild = new GenericNodeImplementation();

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('is not a child of current node');
        $this->node->removeChild($nonChild);
    }

    public function testCannotReplaceNonChild()
    {
        $nonChildOne = new GenericNodeImplementation();
        $nonChildTwo = new GenericNodeImplementation();

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('is not a child of current node');
        $this->node->replaceChild($nonChildOne, $nonChildTwo);
    }

    public function testCannotCreateCycles()
    {
        $father = new GenericNodeImplementation();
        $child  = new GenericNodeImplementation();

        $father->child = $this->node;
        $this->node->child = $child;

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Cannot set a Node or its descendant as its own parent');

        $child->child = $father;
    }

    public function testSetParentNodeRemovesFromPreviousParent()
    {
        $child = new GenericNodeImplementation();
        $node  = new GenericNodeImplementation();

        $this->node->child = $child;
        $child->setParentNode($node);

        $this::assertNull($this->node->child);
        $this::assertSame($node, $child->getParentNode());
    }

    public function testSetPropertyRemovesFromPreviousParent()
    {
        $child = new GenericNodeImplementation();
        $node  = new GenericNodeImplementation();

        $this->node->child = $child;
        $node->setChild($child);

        $this::assertNull($this->node->child);
        $this::assertSame($node, $child->getParentNode());
    }

    public function testReplaceChildRemovesFromPreviousParent()
    {
        $oldChild = new GenericNodeImplementation();
        $newChild = new GenericNodeImplementation();
        $node     = new GenericNodeImplementation();

        $node->child = $oldChild;
        $this->node->child = $newChild;
        $node->replaceChild($oldChild, $newChild);

        $this::assertNull($this->node->child);
        $this::assertSame($newChild, $node->child);
    }

    public function testClonePerformsDeepCloneAndRemovesParentNode()
    {
        $father = new GenericNodeImplementation();
        $child  = new GenericNodeImplementation();

        $father->child = $this->node;
        $this->node->child = $child;

        $cloned = clone $this->node;
        $this::assertNull($cloned->getParentNode());
        $this::assertInstanceOf(Node::class, $cloned->child);
        $this::assertNotSame($this->node->child, $cloned->child);
    }

    public function testUnserializeRestoresParentChildRelationships()
    {
        $father = new GenericNodeImplementation();
        $child  = new GenericNodeImplementation();

        $father->child = $this->node;
        $this->node->child = $child;

        /** @var GenericNodeImplementation $unserialized */
        $unserialized = unserialize(serialize($father));

        $this::assertSame($unserialized, $unserialized->child->getParentNode());
        $this::assertSame($unserialized->child, $unserialized->child->child->getParentNode());
    }

    public function testSetParentNodeToNullRemovesFromParent()
    {
        $child = new GenericNodeImplementation();
        $leaf  = new NonRecursiveNodeImplementation();

        $this->node->child = $child;
        $child->child      = $leaf;

        $leaf->setParentNode(null);
        $child->setParentNode(null);

        $this::assertNull($this->node->child);
        $this::assertNull($child->child);
    }
}
