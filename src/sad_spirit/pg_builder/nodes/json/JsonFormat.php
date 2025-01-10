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

    public function __construct(protected ?JsonEncoding $p_encoding = null)
    {
        $this->generatePropertyNames();
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonFormat($this);
    }
}
