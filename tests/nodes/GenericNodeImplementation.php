<?php

/*
 * This file is part of sad_spirit/pg_builder:
 * query builder for Postgres backed by SQL parser
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\tests\nodes;

use sad_spirit\pg_builder\{
    exceptions\NotImplementedException,
    Node,
    nodes\GenericNode,
    TreeWalker
};

/**
 * An implementation of GenericNode with setNamedProperty() made public
 *
 * @property Node|null $child
 * @property Node|null $readonly
 */
class GenericNodeImplementation extends GenericNode
{
    public int $setChildCalled = 0;
    protected ?Node $p_child = null;
    protected ?Node $p_readonly = null;

    public function __construct(?Node $readonly = null)
    {
        $this->generatePropertyNames();
        $this->setProperty($this->p_readonly, $readonly);
    }

    public function dispatch(TreeWalker $walker): never
    {
        throw new NotImplementedException('Under heavy construction [insert picture of man digging]');
    }

    public function setChild(?Node $child = null): void
    {
        $this->setChildCalled++;

        $this->setProperty($this->p_child, $child);
    }
}
