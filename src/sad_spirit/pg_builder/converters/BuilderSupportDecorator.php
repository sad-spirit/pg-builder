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

use sad_spirit\pg_builder\{
    NativeStatement,
    Parser,
    exceptions\InvalidArgumentException
};
use sad_spirit\pg_builder\nodes\{
    QualifiedName,
    TypeName
};
use sad_spirit\pg_wrapper\{
    Connection,
    TypeConverter
};
use sad_spirit\pg_wrapper\converters\{
    ConfigurableTypeConverterFactory,
    TypeOIDMapper,
    containers\ArrayConverter
};

/**
 * A decorator around ConfigurableTypeConverterFactory supporting pg_builder features
 *
 * Implements support for TypeName nodes and uses Parser for processing type names provided as strings.
 * Using a decorator allows us to wrap e.g. an instance of DefaultTypeConverterFactory pre-configured to handle
 * custom types.
 *
 * @since 2.2.0
 */
class BuilderSupportDecorator implements ConfigurableTypeConverterFactory, TypeNameNodeHandler
{
    /**
     * Mapping "type name as string" => Type name processed by Parser
     * @var array<string, TypeName>
     */
    private array $parsedNames = [];

    public function __construct(private readonly ConfigurableTypeConverterFactory $wrapped, private readonly Parser $parser)
    {
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

    public function setConnection(Connection $connection): void
    {
        $this->wrapped->setConnection($connection);
    }

    public function getConverterForTypeNameNode(TypeName $typeName): TypeConverter
    {
        $baseConverter = $this->wrapped->getConverterForQualifiedName(
            $typeName->name->relation->value,
            $typeName->name->schema?->value,
        );
        return 0 === \count($typeName->bounds) ? $baseConverter : new ArrayConverter($baseConverter);
    }

    public function createTypeNameNodeForOID(int|string $oid): TypeName
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

    public function registerClassMapping(string $className, string $type, string $schema = 'pg_catalog'): void
    {
        $this->wrapped->registerClassMapping($className, $type, $schema);
    }

    public function registerConverter(
        callable|TypeConverter|string $converter,
        array|string $type,
        string $schema = 'pg_catalog'
    ): void {
        $this->wrapped->registerConverter($converter, $type, $schema);
    }

    public function getConverterForQualifiedName(string $typeName, ?string $schemaName = null): TypeConverter
    {
        return $this->wrapped->getConverterForQualifiedName($typeName, $schemaName);
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
            if (!\array_key_exists($name, $parameters)) {
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

        if (\count($converted) < \count($parameters)) {
            $unknown = \array_diff(\array_keys($parameters), \array_keys($converted));
            throw new InvalidArgumentException(
                "Unknown keys in parameters array: '" . \implode("', '", $unknown) . "'"
            );
        }

        return $converted;
    }

    private function getConverterForParsedTypeName(string $typeName): TypeConverter
    {
        if (!isset($this->parsedNames[$typeName])) {
            $this->parsedNames[$typeName] = $this->parser->parseTypeName($typeName);
        }
        return $this->getConverterForTypeNameNode($this->parsedNames[$typeName]);
    }
}
