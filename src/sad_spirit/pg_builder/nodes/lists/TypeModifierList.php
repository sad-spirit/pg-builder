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

namespace sad_spirit\pg_builder\nodes\lists;

use sad_spirit\pg_builder\nodes\expressions\Constant;
use sad_spirit\pg_builder\nodes\Identifier;
use sad_spirit\pg_builder\nodes\NonRecursiveNode;

/**
 * List of type modifiers
 *
 * @extends NonAssociativeList<Constant|Identifier, iterable<Constant|Identifier>, Constant|Identifier>
 */
class TypeModifierList extends NonAssociativeList
{
    use NonRecursiveNode;

    protected static function getAllowedElementClasses(): array
    {
        return [
            Constant::class,
            Identifier::class
        ];
    }
}
