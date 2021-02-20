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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    FunctionLike,
    GenericNode,
    ScalarExpression
};
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Node for parameterless functions with special grammar productions
 *
 * Direct interpretation of SQLValueFunction in src/include/nodes/primnodes.h
 *
 * Previously these functions were converted either to FunctionCall nodes with corresponding functions from pg_catalog,
 * or to TypecastExpression nodes. Better (and probably more compatible) is to keep them as-is.
 *
 * @property-read string               $name
 * @property-read NumericConstant|null $modifier
 */
class SQLValueFunction extends GenericNode implements FunctionLike, ScalarExpression
{
    use ExpressionAtom;

    public const CURRENT_DATE      = 'current_date';
    public const CURRENT_ROLE      = 'current_role';
    public const CURRENT_USER      = 'current_user';
    public const SESSION_USER      = 'session_user';
    public const USER              = 'user';
    public const CURRENT_CATALOG   = 'current_catalog';
    public const CURRENT_SCHEMA    = 'current_schema';

    public const CURRENT_TIME      = 'current_time';
    public const CURRENT_TIMESTAMP = 'current_timestamp';
    public const LOCALTIME         = 'localtime';
    public const LOCALTIMESTAMP    = 'localtimestamp';

    /**
     * These can appear only WITHOUT modifiers
     */
    public const NO_MODIFIERS = [
        self::CURRENT_DATE,
        self::CURRENT_ROLE,
        self::CURRENT_USER,
        self::SESSION_USER,
        self::USER,
        self::CURRENT_CATALOG,
        self::CURRENT_SCHEMA
    ];

    /**
     * These can have modifiers
     */
    public const OPTIONAL_MODIFIERS = [
        self::CURRENT_TIME,
        self::CURRENT_TIMESTAMP,
        self::LOCALTIME,
        self::LOCALTIMESTAMP
    ];

    /**
     * Used for checking incoming $name
     * @var array<string, mixed>|null
     */
    private static $nameCheck;

    /** @var string */
    protected $p_name;
    /** @var NumericConstant|null */
    protected $p_modifier;

    public function __construct(string $name, NumericConstant $modifier = null)
    {
        if (null === self::$nameCheck) {
            self::$nameCheck = array_flip(array_merge(self::NO_MODIFIERS, self::OPTIONAL_MODIFIERS));
        }

        if (!isset(self::$nameCheck[$name])) {
            throw new InvalidArgumentException("Unknown name '{$name}' for SQLValueFunction");
        } elseif (null !== $modifier && !in_array($name, self::OPTIONAL_MODIFIERS)) {
            throw new InvalidArgumentException("SQLValueFunction '{$name}' does not accept modifiers");
        }

        $this->generatePropertyNames();
        $this->p_name     = $name;
        $this->p_modifier = $modifier;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSQLValueFunction($this);
    }
}
