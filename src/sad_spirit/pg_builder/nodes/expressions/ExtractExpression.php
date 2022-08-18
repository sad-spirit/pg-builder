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

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    FunctionLike,
    GenericNode,
    ScalarExpression
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing EXTRACT(field FROM source) expression
 *
 * Previously this was parsed to a FunctionExpression node having pg_catalog.date_part as function name.
 * However, since in Postgres 14 EXTRACT() has changed its mapping to pg_catalog.extract and as Postgres itself
 * now outputs the original SQL standard form of the expression when generating SQL, we follow the suit by
 * creating a separate Node with SQL standard output.
 *
 * $field property is defined as a simple string here since "extract_arg" grammar production accepts either a string
 * constant, an identifier, or several SQL keywords for that argument. Everything that is not a known keyword
 * from {@see ExtractExpression::KEYWORDS} array will be treated as an identifier when generating SQL.
 *
 * @property string           $field
 * @property ScalarExpression $source
 */
class ExtractExpression extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    public const KEYWORDS = ['year', 'month', 'day', 'hour', 'minute', 'second'];

    /** @var string */
    protected $p_field;
    /** @var ScalarExpression */
    protected $p_source;

    public function __construct(string $field, ScalarExpression $source)
    {
        $this->generatePropertyNames();

        $this->p_field = $field;

        $this->p_source = $source;
        $this->p_source->setParentNode($this);
    }

    public function setField(string $field): void
    {
        $this->p_field = $field;
    }

    public function setSource(ScalarExpression $source): void
    {
        $this->setRequiredProperty($this->p_source, $source);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkExtractExpression($this);
    }
}
