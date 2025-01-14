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

namespace sad_spirit\pg_builder\nodes\json;

use sad_spirit\pg_builder\SelectCommon;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing the json_array() expression with a subselect as argument
 *
 * @property SelectCommon    $query
 * @property JsonFormat|null $format
 */
class JsonArraySubselect extends JsonArray
{
    protected SelectCommon $p_query;
    protected ?JsonFormat $p_format = null;

    public function __construct(
        SelectCommon $query,
        ?JsonFormat $format = null,
        ?JsonReturning $returning = null
    ) {
        parent::__construct($returning);

        $this->p_query = $query;
        $this->p_query->setParentNode($this);

        if (null !== $format) {
            $this->p_format = $format;
            $this->p_format->setParentNode($this);
        }
    }

    public function setQuery(SelectCommon $query): void
    {
        $this->setRequiredProperty($this->p_query, $query);
    }

    public function setFormat(?JsonFormat $format): void
    {
        $this->setProperty($this->p_format, $format);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonArraySubselect($this);
    }
}
