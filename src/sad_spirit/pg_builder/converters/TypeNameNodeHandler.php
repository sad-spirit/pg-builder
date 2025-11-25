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

namespace sad_spirit\pg_builder\converters;

use sad_spirit\pg_builder\nodes\TypeName;
use sad_spirit\pg_wrapper\TypeConverter;
use sad_spirit\pg_wrapper\TypeConverterFactory;

/**
 * Interface for type converter factories that can handle TypeName AST Nodes
 *
 * **WARNING**: this interface will likely extend `ConfigurableTypeConverterFactory` in the next major release,
 * consider this when implementing
 */
interface TypeNameNodeHandler extends /*Configurable*/TypeConverterFactory
{
    /**
     * Returns a converter for query builder's TypeName node
     *
     * Usually this will come from a typecast applied to a query parameter and extracted by ParameterWalker
     */
    public function getConverterForTypeNameNode(TypeName $typeName): TypeConverter;

    /**
     * Returns TypeName node for query AST based on provided type OID
     *
     * @param int|numeric-string $oid
     */
    public function createTypeNameNodeForOID(int|string $oid): TypeName;
}
