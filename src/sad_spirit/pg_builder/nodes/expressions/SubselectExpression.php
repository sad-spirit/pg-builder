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
 * @copyright 2014-2022 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\{
    SelectCommon,
    TreeWalker,
    exceptions\InvalidArgumentException,
    nodes\ExpressionAtom,
    nodes\GenericNode,
    nodes\ScalarExpression
};

/**
 * AST node representing a subquery appearing in scalar expressions, possibly with a subquery operator applied
 *
 * @property      SelectCommon $query
 * @property-read string|null  $operator
 */
class SubselectExpression extends GenericNode implements ScalarExpression
{
    use ExpressionAtom;

    public const EXISTS = 'exists';
    public const ANY    = 'any';
    public const ALL    = 'all';
    public const SOME   = 'some';
    public const ARRAY  = 'array';

    private const ALLOWED_EXPRESSIONS = [
        self::EXISTS => true,
        self::ANY    => true,
        self::ALL    => true,
        self::SOME   => true,
        self::ARRAY  => true
        // "in" is served by InExpression
    ];

    /** @var SelectCommon */
    protected $p_query;
    /** @var string|null */
    protected $p_operator;

    public function __construct(SelectCommon $query, ?string $operator = null)
    {
        if (null !== $operator && !isset(self::ALLOWED_EXPRESSIONS[$operator])) {
            throw new InvalidArgumentException("Unknown subquery operator '{$operator}'");
        }

        $this->generatePropertyNames();
        $this->p_query = $query;
        $this->p_query->setParentNode($this);
        $this->p_operator = $operator;
    }

    public function setQuery(SelectCommon $query): void
    {
        $this->setRequiredProperty($this->p_query, $query);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSubselectExpression($this);
    }
}
