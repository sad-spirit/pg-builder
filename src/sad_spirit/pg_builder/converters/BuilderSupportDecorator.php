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

namespace sad_spirit\pg_builder\converters;

use sad_spirit\pg_builder\Parser;
use sad_spirit\pg_builder\nodes\{
    QualifiedName,
    TypeName
};
use sad_spirit\pg_wrapper\{
    Connection,
    TypeConverter,
    TypeConverterFactory,
    exceptions\InvalidArgumentException
};
use sad_spirit\pg_wrapper\converters\{
    DefaultTypeConverterFactory,
    TypeOIDMapper,
    TypeOIDMapperAware
};

/**
 * A decorator around DefaultTypeConverterFactory supporting pg_builder features
 *
 * Implements support for TypeName nodes and uses Parser for processing type names provided as strings.
 * Using a decorator allows us to wrap e.g. an instance of DefaultTypeConverterFactory pre-configured to handle
 * custom types.
 *
 * @since 2.2.0
 */
class BuilderSupportDecorator implements TypeNameNodeHandler, TypeOIDMapperAware
{
    use ParametersConverter;

    /** @var DefaultTypeConverterFactory */
    private $wrapped;
    /** @var Parser */
    private $parser;
    /**
     * Mapping "type name as string" => Type name processed by Parser
     * @var array<string, TypeName>
     */
    private $parsedNames = [];

    public function __construct(DefaultTypeConverterFactory $wrapped, Parser $parser)
    {
        $this->wrapped = $wrapped;
        $this->parser = $parser;
    }

    public function getConverterForTypeSpecification($type): TypeConverter
    {
        if ($type instanceof TypeName) {
            return $this->getConverterForTypeNameNode($type);
        } elseif (\is_string($type)) {
            return $this->getConverterForParsedTypeName($type);
        } else {
            return $this->wrapped->getConverterForTypeSpecification($type);
        }
    }

    public function getConverterForTypeOID($oid): TypeConverter
    {
        return $this->wrapped->getConverterForTypeOID($oid);
    }

    public function getConverterForPHPValue($value): TypeConverter
    {
        return $this->wrapped->getConverterForPHPValue($value);
    }

    public function setConnection(Connection $connection): TypeConverterFactory
    {
        $this->wrapped->setConnection($connection);

        return $this;
    }

    public function getConverterForTypeNameNode(TypeName $typeName): TypeConverter
    {
        return $this->wrapped->getConverterForQualifiedName(
            $typeName->name->relation->value,
            $typeName->name->schema !== null ? $typeName->name->schema->value : null,
            \count($typeName->bounds) > 0
        );
    }

    public function createTypeNameNodeForOID($oid): TypeName
    {
        if ($this->getOIDMapper()->isArrayTypeOID($oid, $baseTypeOid)) {
            $node = $this->createTypeNameNodeForOID($baseTypeOid);
            $node->bounds = [-1];
            return $node;

        } else {
            [$schemaName, $typeName] = $this->getOIDMapper()->findTypeNameForOID($oid);
            if ('pg_catalog' !== $schemaName) {
                return new TypeName(new QualifiedName($schemaName, $typeName));
            } else {
                return new TypeName(new QualifiedName($typeName));
            }
        }
    }

    public function setOIDMapper(TypeOIDMapper $mapper): void
    {
        $this->wrapped->setOIDMapper($mapper);
    }

    public function getOIDMapper(): TypeOIDMapper
    {
        return $this->wrapped->getOIDMapper();
    }

    /**
     * Registers a mapping between PHP class and database type name
     *
     * If an instance of the given class will later be provided to getConverterForPHPValue(), that method will return
     * a converter for the given database type
     *
     * @param class-string $className
     * @param string       $type
     * @param string       $schema
     */
    public function registerClassMapping(string $className, string $type, string $schema = 'pg_catalog'): void
    {
        $this->wrapped->registerClassMapping($className, $type, $schema);
    }

    /**
     * Registers a converter for a known named type
     *
     * @param class-string<TypeConverter>|callable|TypeConverter $converter
     * @param string|string[]                     $type
     * @param string                              $schema
     * @throws InvalidArgumentException
     */
    public function registerConverter($converter, $type, string $schema = 'pg_catalog'): void
    {
        $this->wrapped->registerConverter($converter, $type, $schema);
    }

    private function getConverterForParsedTypeName(string $typeName): TypeConverter
    {
        if (!isset($this->parsedNames[$typeName])) {
            $this->parsedNames[$typeName] = $this->parser->parseTypeName($typeName);
        }
        return $this->getConverterForTypeNameNode($this->parsedNames[$typeName]);
    }
}
