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

namespace sad_spirit\pg_builder\converters;

use sad_spirit\pg_builder\{
    Parser,
    nodes\IntervalTypeName,
    nodes\QualifiedName,
    nodes\TypeName
};
use sad_spirit\pg_wrapper\{
    TypeConverter,
    converters\DefaultTypeConverterFactory
};

/**
 * Adds methods for TypeName AST nodes handling and possibility to use Parser to process type names
 */
class ParserAwareTypeConverterFactory extends DefaultTypeConverterFactory
{
    /** @var Parser|null */
    private $parser;

    public function __construct(Parser $parser = null)
    {
        parent::__construct();
        $this->setParser($parser);
    }

    /**
     * Sets a Parser that will be used to process type specifications given as strings
     *
     * @param Parser|null $parser
     * @return $this
     */
    public function setParser(Parser $parser = null): self
    {
        $this->parser = $parser;

        return $this;
    }

    /**
     * Returns a converter for a given database type
     *
     * In addition to type specifications accepted by parent class this will also accept
     * TypeName nodes.
     *
     * @param mixed $type
     * @return TypeConverter
     */
    public function getConverterForTypeSpecification($type): TypeConverter
    {
        return $type instanceof TypeName
            ? $this->getConverterForTypeNameNode($type)
            : parent::getConverterForTypeSpecification($type);
    }


    /**
     * Returns a converter for query builder's TypeName node
     *
     * Usually this will come from a typecast applied to a query parameter and
     * extracted by ParameterWalker
     *
     * @param TypeName $typeName
     * @return TypeConverter
     */
    private function getConverterForTypeNameNode(TypeName $typeName): TypeConverter
    {
        if ($typeName instanceof IntervalTypeName) {
            return $this->getConverterForQualifiedName('interval', 'pg_catalog', count($typeName->bounds) > 0);
        } else {
            return $this->getConverterForQualifiedName(
                $typeName->name->relation->value,
                $typeName->name->schema ? $typeName->name->schema->value : null,
                count($typeName->bounds) > 0
            );
        }
    }

    /**
     * Parses any type name Postgres itself understands
     *
     * If Parser was not provided, falls back to DefaultTypeConverterFactory implementation,
     * so consider its shortcomings.
     *
     * @param string $name
     * @return array
     */
    protected function parseTypeName(string $name): array
    {
        if (!$this->parser) {
            return parent::parseTypeName($name);
        } else {
            $node = $this->parser->parseTypeName($name);

            if ($node instanceof IntervalTypeName) {
                return ['pg_catalog', 'interval', count($node->bounds) > 0];
            } else {
                return [
                    $node->name->schema ? $node->name->schema->value : null,
                    $node->name->relation->value,
                    count($node->bounds) > 0
                ];
            }
        }
    }

    /**
     * Returns TypeName node for query AST based on provided type oid
     *
     * @param int $oid
     * @return TypeName
     */
    public function createTypeNameNodeForOID($oid)
    {
        if ($this->isArrayTypeOid($oid, $baseTypeOid)) {
            $node = $this->createTypeNameNodeForOID($baseTypeOid);
            $node->bounds = [-1];
            return $node;

        } else {
            [$schemaName, $typeName] = $this->findTypeNameForOID($oid, __METHOD__);
            if ('pg_catalog' !== $schemaName) {
                return new TypeName(new QualifiedName([$schemaName, $typeName]));
            } else {
                return new TypeName(new QualifiedName([$typeName]));
            }
        }
    }
}
