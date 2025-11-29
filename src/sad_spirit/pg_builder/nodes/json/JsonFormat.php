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

use sad_spirit\pg_builder\{
    enums\JsonEncoding,
    nodes\GenericNode,
    nodes\NonRecursiveNode,
    TreeWalker
};

/**
 * Represents the FORMAT clause in various JSON expressions
 *
 * Looks like it is 100% noise right now, as 'json' format is hardcoded in grammar
 * and no encodings other than utf-8 work
 *
 * @property-read JsonEncoding|null $encoding
 */
class JsonFormat extends GenericNode
{
    use NonRecursiveNode;

    /** @internal Maps to `$encoding` magic property, use the latter instead */
    protected ?JsonEncoding $p_encoding = null;

    public function __construct(?JsonEncoding $encoding = null)
    {
        $this->generatePropertyNames();

        $this->p_encoding = $encoding;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonFormat($this);
    }
}
