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

namespace sad_spirit\pg_builder\tests\nodes;

use sad_spirit\pg_builder\Node;
use sad_spirit\pg_builder\nodes\lists\GenericNodeList;

/**
 * A non-abstract subclass of GenericNodeList
 * @extends GenericNodeList<int|string, Node, iterable<Node>, Node>
 */
class GenericNodeListImplementation extends GenericNodeList
{
    protected function convertToArray($list, string $method): array
    {
        return [];
    }
}
