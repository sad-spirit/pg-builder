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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    GenericNode,
    ScalarExpression
};
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Represents a keyword ANY / ALL / SOME applied to an array-type expression
 *
 * @property string           $keyword
 * @property ScalarExpression $array
 */
class ArrayComparisonExpression extends GenericNode implements ScalarExpression
{
    use ExpressionAtom;

    public const ANY  = 'any';
    public const ALL  = 'all';
    public const SOME = 'some';

    private const ALLOWED_KEYWORDS = [
        self::ANY  => true,
        self::ALL  => true,
        self::SOME => true
    ];

    /** @var string */
    protected $p_keyword;
    /** @var ScalarExpression */
    protected $p_array;

    public function __construct(string $keyword, ScalarExpression $array)
    {
        if (!isset(self::ALLOWED_KEYWORDS[$keyword])) {
            throw new InvalidArgumentException("Unknown keyword '{$keyword}' for ArrayComparisonExpression");
        }

        $this->generatePropertyNames();

        $this->p_keyword = $keyword;

        $this->p_array = $array;
        $this->p_array->setParentNode($this);
    }

    public function setArray(ScalarExpression $array): void
    {
        $this->setRequiredProperty($this->p_array, $array);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkArrayComparisonExpression($this);
    }
}
