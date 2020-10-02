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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\{
    exceptions\InvalidArgumentException,
    exceptions\SyntaxException,
    TreeWalker
};

/**
 * Represents a (possibly qualified) name of a database object like a relation, function or type name
 *
 * @property-read Identifier|null $catalog
 * @property-read Identifier|null $schema
 * @property-read Identifier      $relation
 */
class QualifiedName extends GenericNode
{
    use LeafNode;

    /** @noinspection PhpMissingBreakStatementInspection */
    public function __construct(array $nameParts)
    {
        $this->props = [
            'catalog'  => null,
            'schema'   => null,
            'relation' => null
        ];

        foreach ($nameParts as $idx => &$part) {
            if (is_string($part)) {
                $part = new Identifier($part);

            } elseif (!($part instanceof Identifier)) {
                throw new InvalidArgumentException(sprintf(
                    '%s expects an array containing strings or Identifiers, %s given at index %s',
                    __CLASS__,
                    is_object($part) ? 'object(' . get_class($part) . ')' : gettype($part),
                    $idx
                ));
            }
        }

        switch (count($nameParts)) {
            case 3:
                $this->setNamedProperty('catalog', array_shift($nameParts)); // fall-through is intentional
            case 2:
                $this->setNamedProperty('schema', array_shift($nameParts)); // fall-through is intentional
            case 1:
                $this->setNamedProperty('relation', array_shift($nameParts)); // fall-through is intentional
                break;
            case 0:
                throw new InvalidArgumentException(
                    __CLASS__ . ' expects an array containing strings or Identifiers, empty array given'
                );
            default:
                throw new SyntaxException("Too many dots in qualified name: " . implode('.', $nameParts));
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkQualifiedName($this);
    }

    /**
     * This is only used for constructing exception messages
     *
     * @return string
     */
    public function __toString()
    {
        return ($this->catalog ? $this->catalog->__toString() . '.' : '')
               . ($this->schema ? $this->schema->__toString() . '.' : '')
               . $this->relation->__toString();
    }
}
