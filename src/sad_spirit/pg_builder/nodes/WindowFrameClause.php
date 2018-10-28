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
 * @copyright 2014-2018 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing the window frame (opt_frame_clause production in grammar)
 *
 * @property-read string           $type
 * @property-read WindowFrameBound $start
 * @property-read WindowFrameBound $end
 */
class WindowFrameClause extends Node
{
    protected static $allowedTypes = array(
        'range' => true,
        'rows'  => true
    );

    public function __construct($type, WindowFrameBound $start, WindowFrameBound $end = null)
    {
        if (!isset(self::$allowedTypes[$type])) {
            throw new InvalidArgumentException("Unknown window frame type '{$type}'");
        }
        $this->props['type'] = (string)$type;
        $this->setNamedProperty('start', $start);
        $this->setNamedProperty('end', $end);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkWindowFrameClause($this);
    }
}