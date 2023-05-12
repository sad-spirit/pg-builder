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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\converters;

use sad_spirit\pg_builder\nodes\TypeName;
use sad_spirit\pg_wrapper\TypeConverter;
use sad_spirit\pg_wrapper\TypeConverterFactory;

/**
 * Interface for type converter factories that can handle TypeName AST Nodes
 */
interface TypeNameNodeHandler extends TypeConverterFactory
{
    /**
     * Returns a converter for query builder's TypeName node
     *
     * Usually this will come from a typecast applied to a query parameter and extracted by ParameterWalker
     *
     * @param TypeName $typeName
     * @return TypeConverter
     */
    public function getConverterForTypeNameNode(TypeName $typeName): TypeConverter;

    /**
     * Returns TypeName node for query AST based on provided type OID
     *
     * @param int|numeric-string $oid
     * @return TypeName
     */
    public function createTypeNameNodeForOID($oid): TypeName;
}
