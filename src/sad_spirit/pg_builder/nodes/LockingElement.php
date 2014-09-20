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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\nodes\lists\NonAssociativeList,
    sad_spirit\pg_wrapper\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\TreeWalker;

/**
 * AST node for locking options in SELECT clause
 *
 * @property-read string $strength
 * @property-read bool   $noWait
 */
class LockingElement extends NonAssociativeList
{
    protected static $allowedStrengths = array(
        'update'        => true,
        'no key update' => true,
        'share'         => true,
        'key share'     => true
    );

    public function __construct($strength, array $relations = array(), $noWait = false)
    {
        if (!isset(self::$allowedStrengths[$strength])) {
            throw new InvalidArgumentException("Unknown locking strength '{$strength}'");
        }

        $this->props['strength'] = (string)$strength;
        $this->props['noWait']   = (bool)$noWait;
        parent::__construct($relations);
    }

    protected function normalizeElement(&$offset, &$value)
    {
        parent::normalizeElement($offset, $value);

        if (!($value instanceof QualifiedName)) {
            throw new InvalidArgumentException(sprintf(
                '%s can contain only instances of QualifiedName, %s given',
                __CLASS__, is_object($value) ? 'object(' . get_class($value) . ')' : gettype($value)
            ));
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkLockingElement($this);
    }

    /**
     * Checks in base setParentNode() are redundant as this can only contain QualifiedName's
     *
     * @param Node $parent
     */
    protected function setParentNode(Node $parent = null)
    {
        if ($parent && $this->parentNode && $parent !== $this->parentNode) {
            $this->parentNode->removeChild($this);
        }
        $this->parentNode = $parent;
    }
}
