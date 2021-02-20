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

namespace sad_spirit\pg_builder\converters;

use sad_spirit\pg_builder\{
    NativeStatement,
    Parser,
    exceptions\InvalidArgumentException,
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
        return $this->getConverterForQualifiedName(
            $typeName->name->relation->value,
            $typeName->name->schema !== null ? $typeName->name->schema->value : null,
            count($typeName->bounds) > 0
        );
    }

    /**
     * Parses any type name Postgres itself understands
     *
     * If Parser was not provided, falls back to DefaultTypeConverterFactory implementation,
     * so consider its shortcomings.
     *
     * {@inheritDoc}
     */
    protected function parseTypeName(string $name): array
    {
        if (null === $this->parser) {
            return parent::parseTypeName($name);
        } else {
            $node = $this->parser->parseTypeName($name);

            return [
                null !== $node->name->schema ? $node->name->schema->value : null,
                $node->name->relation->value,
                count($node->bounds) > 0
            ];
        }
    }

    /**
     * Returns TypeName node for query AST based on provided type OID
     *
     * @param int $oid
     * @return TypeName
     */
    public function createTypeNameNodeForOID(int $oid): TypeName
    {
        if ($this->isArrayTypeOID($oid, $baseTypeOid)) {
            $node = $this->createTypeNameNodeForOID($baseTypeOid);
            $node->bounds = [-1];
            return $node;

        } else {
            [$schemaName, $typeName] = $this->findTypeNameForOID($oid, __METHOD__);
            if ('pg_catalog' !== $schemaName) {
                return new TypeName(new QualifiedName($schemaName, $typeName));
            } else {
                return new TypeName(new QualifiedName($typeName));
            }
        }
    }

    /**
     * Converts query parameters according to types stored in NativeStatement
     *
     * This is a convenience method for using NativeStatement with PDO, the resultant array may be directly
     * fed to PDOStatement::execute():
     * <code>
     * $stmt = $pdo->prepare($native->getSql());
     * $stmt->execute($factory->convertParameters($native, $params));
     * </code>
     *
     * @param NativeStatement      $statement
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $paramTypes Additional parameter types, values from this array will take precedence
     *                                         over types from $statement->getParameterTypes()
     * @return array<string, ?string>
     * @throws InvalidArgumentException
     */
    public function convertParameters(NativeStatement $statement, array $parameters, array $paramTypes = []): array
    {
        $inferredTypes = $statement->getParameterTypes();
        $converted     = [];
        foreach ($statement->getNamedParameterMap() as $name => $index) {
            if (!array_key_exists($name, $parameters)) {
                throw new InvalidArgumentException("Missing parameter name '{$name}'");
            }
            if (!empty($paramTypes[$name])) {
                $converter = $this->getConverterForTypeSpecification($paramTypes[$name]);
            } elseif (!empty($inferredTypes[$index])) {
                $converter = $this->getConverterForTypeNameNode($inferredTypes[$index]);
            } else {
                $converter = $this->getConverterForPHPValue($parameters[$name]);
            }
            $converted[$name] = $converter->output($parameters[$name]);
        }

        if (count($converted) < count($parameters)) {
            $unknown = array_diff(array_keys($parameters), array_keys($converted));
            throw new InvalidArgumentException(
                "Unknown keys in parameters array: '" . implode("', '", $unknown) . "'"
            );
        }

        return $converted;
    }
}
