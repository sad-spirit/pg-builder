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
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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
    /** @var SelectCommon */
    protected $p_query;
    /** @var JsonFormat|null */
    protected $p_format;

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

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonArraySubselect($this);
    }
}
