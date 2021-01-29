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

namespace sad_spirit\pg_builder\nodes\group;

use sad_spirit\pg_builder\nodes\HasBothPropsAndOffsets;
use sad_spirit\pg_builder\nodes\lists\ExpressionList;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing CUBE(...) and ROLLUP(...) constructs in GROUP BY clause
 *
 * @property string $type
 */
class CubeOrRollupClause extends ExpressionList implements GroupByElement
{
    use HasBothPropsAndOffsets;

    public const CUBE   = 'cube';
    public const ROLLUP = 'rollup';

    private const ALLOWED_TYPES = [
        self::CUBE   => true,
        self::ROLLUP => true
    ];

    /** @var string */
    protected $p_type = self::CUBE;

    public function __construct($list = null, string $type = self::CUBE)
    {
        $this->generatePropertyNames();
        parent::__construct($list);
        $this->setType($type);
    }

    public function setType(string $type): void
    {
        if (!isset(self::ALLOWED_TYPES[$type])) {
            throw new InvalidArgumentException("Unknown grouping set type '{$type}'");
        }
        $this->p_type = $type;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkCubeOrRollupClause($this);
    }
}
