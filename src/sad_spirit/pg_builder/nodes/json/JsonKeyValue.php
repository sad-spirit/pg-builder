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

namespace sad_spirit\pg_builder\nodes\json;

use sad_spirit\pg_builder\{
    nodes\GenericNode,
    nodes\ScalarExpression,
    TreeWalker
};

/**
 * Represents a key-value pair for JSON
 *
 * @property ScalarExpression $key
 * @property JsonValue        $value
 */
class JsonKeyValue extends GenericNode
{
    /** @var ScalarExpression */
    protected $p_key;
    /** @var JsonValue */
    protected $p_value;

    public function __construct(ScalarExpression $key, JsonValue $value)
    {
        $this->generatePropertyNames();

        $this->p_key = $key;
        $this->p_key->setParentNode($this);

        $this->p_value = $value;
        $this->p_value->setParentNode($this);
    }

    public function setKey(ScalarExpression $key): void
    {
        $this->setRequiredProperty($this->p_key, $key);
    }

    public function setValue(JsonValue $value): void
    {
        $this->setRequiredProperty($this->p_value, $value);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonKeyValue($this);
    }
}
