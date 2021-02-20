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
    /** @var int */
    public $setChildCalled = 0;
    /** @var Node|null */
    protected $p_child;
    /** @var Node|null */
    protected $p_readonly;

    public function __construct(Node $readonly = null)
    {
        $this->generatePropertyNames();
        $this->setProperty($this->p_readonly, $readonly);
    }

    public function dispatch(TreeWalker $walker)
    {
        throw new NotImplementedException('Under heavy construction [insert picture of man digging]');
    }

    public function setChild(Node $child = null): void
    {
        $this->setChildCalled++;

        $this->setProperty($this->p_child, $child);
    }
}
