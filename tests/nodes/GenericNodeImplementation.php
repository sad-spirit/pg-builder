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
    public $setChildCalled = 0;

    public function __construct(Node $readonly = null)
    {
        $this->props['child'] = null;
        $this->setNamedProperty('readonly', $readonly);
    }

    public function setNamedProperty(string $propertyName, $propertyValue): void
    {
        parent::setNamedProperty($propertyName, $propertyValue);
    }

    public function dispatch(TreeWalker $walker)
    {
        throw new NotImplementedException('Under heavy construction [insert picture of man digging]');
    }

    public function setChild(Node $child = null)
    {
        $this->setChildCalled++;

        $this->setNamedProperty('child', $child);
    }
}