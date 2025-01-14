<?php

/*
 * This file is part of sad_spirit/pg_builder:
 * query builder for Postgres backed by SQL parser
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    FunctionLike,
    GenericNode,
    ScalarExpression
};
use sad_spirit\pg_builder\enums\ExtractPart;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing EXTRACT(field FROM source) expression
 *
 * Previously this was parsed to a FunctionExpression node having pg_catalog.date_part as function name.
 * However, since in Postgres 14 EXTRACT() has changed its mapping to pg_catalog.extract and as Postgres itself
 * now outputs the original SQL standard form of the expression when generating SQL, we follow the suit by
 * creating a separate Node with SQL standard output.
 *
 * As "extract_arg" grammar production accepts either a string constant, an identifier, or several SQL keywords
 * for the first argument, it can be either a string or {@see ExtractPart}. Everything that is not a known keyword
 * represented by the latter will be treated as an identifier when generating SQL.
 *
 * @property string|ExtractPart $field
 * @property ScalarExpression   $source
 */
class ExtractExpression extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    protected ExtractPart|string $p_field;
    protected ScalarExpression $p_source;

    public function __construct(string|ExtractPart $field, ScalarExpression $source)
    {
        $this->generatePropertyNames();

        $this->p_field  = $field;
        $this->p_source = $source;
        $this->p_source->setParentNode($this);
    }

    public function setField(string|ExtractPart $field): void
    {
        $this->p_field = $field;
    }

    public function setSource(ScalarExpression $source): void
    {
        $this->setRequiredProperty($this->p_source, $source);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkExtractExpression($this);
    }
}
