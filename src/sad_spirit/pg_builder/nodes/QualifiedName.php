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
    use NonRecursiveNode;

    protected $props = [
        'catalog'  => null,
        'schema'   => null,
        'relation' => null
    ];

    /**
     * QualifiedName constructor, requires at least one name part, accepts up to three
     *
     * @param string|Identifier ...$nameParts
     * @noinspection PhpMissingBreakStatementInspection
     */
    public function __construct(...$nameParts)
    {
        switch (count($nameParts)) {
            case 3:
                $this->props['catalog'] = $this->expectIdentifier(array_shift($nameParts), 'catalog');
                $this->props['catalog']->setParentNode($this);
                // fall-through is intentional
            case 2:
                $this->props['schema'] = $this->expectIdentifier(array_shift($nameParts), 'schema');
                $this->props['schema']->setParentNode($this);
                // fall-through is intentional
            case 1:
                $this->props['relation'] = $this->expectIdentifier(array_shift($nameParts), 'relation');
                $this->props['relation']->setParentNode($this);
                break;

            case 0:
                throw new InvalidArgumentException(__CLASS__ . ' constructor expects at least one name part');
            default:
                throw new SyntaxException("Too many dots in qualified name: " . implode('.', $nameParts));
        }
    }

    /**
     * Tries to convert part of qualified name to Identifier
     *
     * @param mixed $namePart
     * @param string $index
     * @return Identifier
     */
    private function expectIdentifier($namePart, string $index): Identifier
    {
        if ($namePart instanceof Identifier) {
            return $namePart;
        }
        try {
            return new Identifier($namePart);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(sprintf(
                "%s: %s part of qualified name could not be converted to Identifier; %s",
                __CLASS__,
                $index,
                $e->getMessage()
            ));
        }
    }

    public function serialize(): string
    {
        return serialize(array_map(function ($prop) {
            return $prop instanceof Identifier ? $prop->value : $prop;
        }, $this->props));
    }

    public function unserialize($serialized)
    {
        $this->props = array_map(function ($prop) {
            if (null !== $prop) {
                $prop = new Identifier($prop);
                $prop->parentNode = $this;
            }
            return $prop;
        }, unserialize($serialized));
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkQualifiedName($this);
    }

    /**
     * Returns the string representation of the node, with double quotes added as needed
     *
     * @return string
     */
    public function __toString()
    {
        return (null === $this->props['catalog'] ? '' : (string)$this->props['catalog'] . '.')
            . (null === $this->props['schema'] ? '' : (string)$this->props['schema'] . '.')
            . (string)$this->props['relation'];
    }
}
