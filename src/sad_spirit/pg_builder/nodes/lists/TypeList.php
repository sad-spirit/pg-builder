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
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\lists;

use sad_spirit\pg_builder\nodes\NonRecursiveNode;
use sad_spirit\pg_builder\nodes\TypeName;

/**
 * List of type names (only appears in IS OF?)
 *
 * @extends NonAssociativeList<TypeName, iterable<TypeName>, TypeName>
 * @deprecated This is only used by IsOfExpression and will be removed alongside it
 */
class TypeList extends NonAssociativeList
{
    use NonRecursiveNode;

    public function __construct($list = null)
    {
        @trigger_error(
            "Undocumented IS [NOT] OF expressions will be removed in Postgres 14 "
            . "and in the next pg_builder version with Postgres 14 support",
            \E_USER_DEPRECATED
        );
        parent::__construct($list);
    }

    protected static function getAllowedElementClasses(): array
    {
        return [TypeName::class];
    }
}
