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

namespace sad_spirit\pg_builder;

use sad_spirit\pg_wrapper\{
    Connection,
    PreparedStatement,
    ResultSet,
    exceptions\ServerException
};

/**
 * Wraps the results of query building process, can be serialized and stored in cache
 */
class NativeStatement
{
    /**
     * SQL statement
     * @var string
     */
    private $sql;

    /**
     * Mapping 'parameter name' => 'parameter position' if named parameters were used
     * @var array<string, int>
     */
    private $namedParameterMap;

    /**
     * Type info: 'parameter position' => 'parameter type' if explicit typecasts were used for parameters
     * @var array<int, ?nodes\TypeName>
     */
    private $parameterTypes;

    /**
     * @var PreparedStatement|null
     */
    private $preparedStatement;

    /**
     * Constructor, sets the query building results
     *
     * @param string                      $sql
     * @param array<int, ?nodes\TypeName> $parameterTypes
     * @param array<string, int>          $namedParameterMap
     */
    public function __construct(string $sql, array $parameterTypes, array $namedParameterMap)
    {
        $this->sql               = $sql;
        $this->parameterTypes    = $parameterTypes;
        $this->namedParameterMap = $namedParameterMap;
    }

    /**
     * Prevents serialization of $preparedStatement property
     */
    public function __sleep(): array
    {
        return ['sql', 'parameterTypes', 'namedParameterMap'];
    }

    /**
     * Returns the SQL statement string
     *
     * @return string
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Returns mapping from named parameters to positional ones
     *
     * @return array<string, int>
     */
    public function getNamedParameterMap(): array
    {
        return $this->namedParameterMap;
    }

    /**
     * Returns known types for parameters (if parameters were used with type casts)
     *
     * @return array<int, ?nodes\TypeName>
     */
    public function getParameterTypes(): array
    {
        return $this->parameterTypes;
    }

    /**
     * Converts parameters array keyed with parameters' names to positional array
     *
     * @param array<string, mixed> $parameters
     * @return array<int, mixed>
     * @throws exceptions\InvalidArgumentException
     */
    public function mapNamedParameters(array $parameters): array
    {
        $positional = [];
        foreach ($this->namedParameterMap as $name => $position) {
            if (!array_key_exists($name, $parameters)) {
                throw new exceptions\InvalidArgumentException("Missing parameter name '{$name}'");
            }
            $positional[$position] = $parameters[$name];
        }
        if (count($positional) < count($parameters)) {
            $unknown = array_diff(array_keys($parameters), array_keys($this->namedParameterMap));
            throw new exceptions\InvalidArgumentException(
                "Unknown keys in parameters array: '" . implode("', '", $unknown) . "'"
            );
        }
        return $positional;
    }

    /**
     * Merges the types array received from builder with additional types info
     *
     * @param mixed[] $paramTypes Parameter types (keys can be either names or positions), types from this
     *                            array take precedence over types from $parameterTypes
     * @return array<int, mixed>
     * @throws exceptions\InvalidArgumentException
     */
    public function mergeParameterTypes(array $paramTypes): array
    {
        $types = $this->parameterTypes;
        foreach ($paramTypes as $key => $type) {
            if (array_key_exists($key, $types)) {
                $types[$key] = $type;
            } elseif (array_key_exists($key, $this->namedParameterMap)) {
                $types[$this->namedParameterMap[$key]] = $type;
            } else {
                throw new exceptions\InvalidArgumentException(
                    "Offset '{$key}' in input types array does not correspond to a known parameter"
                );
            }
        }
        return $types;
    }

    /**
     * Executes the query with the ability to pass parameters separately
     *
     * @param Connection $connection  DB connection
     * @param mixed[]    $params      Parameters (keys are treated as names unless $namedParameterMap is empty)
     * @param mixed[]    $paramTypes  Parameter types (keys can be either names or positions), types from this
     *                                array take precedence over types from $parameterTypes property
     * @param mixed[]    $resultTypes Result types to pass to ResultSet (keys can be either names or positions)
     * @return ResultSet
     * @throws ServerException
     * @throws exceptions\InvalidArgumentException
     */
    public function executeParams(
        Connection $connection,
        array $params,
        array $paramTypes = [],
        array $resultTypes = []
    ): ResultSet {
        if (empty($this->namedParameterMap)) {
            return $connection->executeParams(
                $this->sql,
                $params,
                $this->mergeParameterTypes($paramTypes),
                $resultTypes
            );
        } else {
            return $connection->executeParams(
                $this->sql,
                $this->mapNamedParameters($params),
                $this->mergeParameterTypes($paramTypes),
                $resultTypes
            );
        }
    }

    /**
     * Prepares the query for execution.
     *
     * @param Connection $connection DB connection
     * @param mixed[]    $paramTypes Parameter types (keys can be either names or positions), types from this
     *                               array take precedence over types from $parameterTypes property
     * @return PreparedStatement
     */
    public function prepare(Connection $connection, array $paramTypes = []): PreparedStatement
    {
        $this->preparedStatement = $connection->prepare($this->sql, $this->mergeParameterTypes($paramTypes));
        return $this->preparedStatement;
    }

    /**
     * Executes the prepared statement (requires prepare() to be called first)
     *
     * @param mixed[] $params      Parameters (keys are treated as names unless $namedParameterMap is empty)
     * @param mixed[] $resultTypes Result types to pass to ResultSet (keys can be either names or positions)
     * @return ResultSet
     * @throws exceptions\RuntimeException
     * @throws ServerException
     */
    public function executePrepared(array $params = [], array $resultTypes = []): ResultSet
    {
        if (null === $this->preparedStatement) {
            throw new exceptions\RuntimeException(__METHOD__ . '(): prepare() should be called first');
        }
        if (empty($this->namedParameterMap)) {
            return $this->preparedStatement->execute($params, $resultTypes);
        } else {
            return $this->preparedStatement->execute($this->mapNamedParameters($params), $resultTypes);
        }
    }
}
