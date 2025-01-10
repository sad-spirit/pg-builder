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

namespace sad_spirit\pg_builder;

use sad_spirit\pg_wrapper\{
    Connection,
    PreparedStatement,
    Result,
    exceptions\ServerException
};

/**
 * Wraps the results of query building process, can be serialized and stored in cache
 */
class NativeStatement
{
    private ?PreparedStatement $preparedStatement = null;

    /**
     * Constructor, sets the query building results
     *
     * @param string                      $sql               SQL statement
     * @param array<int, ?nodes\TypeName> $parameterTypes    Type info: 'parameter position' => 'parameter type'
     *                                                       if explicit typecasts were used for parameters
     * @param array<string, int>          $namedParameterMap Mapping 'parameter name' => 'parameter position'
     *                                                       if named parameters were used
     */
    public function __construct(
        private readonly string $sql,
        private readonly array $parameterTypes,
        private readonly array $namedParameterMap
    ) {
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
     * @param array $paramTypes Parameter types (keys can be either names or positions), types from this
     *                          array take precedence over types from $parameterTypes
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
     * @param array $params      Parameters (keys are treated as names unless $namedParameterMap is empty)
     * @param array $paramTypes  Parameter types (keys can be either names or positions), types from this
     *                           array take precedence over types from $parameterTypes property
     * @param array $resultTypes Result types to pass to Result (keys can be either names or positions)
     * @return Result
     * @throws ServerException
     * @throws exceptions\InvalidArgumentException
     */
    public function executeParams(
        Connection $connection,
        array $params,
        array $paramTypes = [],
        array $resultTypes = []
    ): Result {
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
     * @param Connection $connection  DB connection
     * @param array      $paramTypes  Parameter types (keys can be either names or positions), types from this
     *                                array take precedence over types from $parameterTypes property
     * @param array      $resultTypes Result types to pass to Result instances
     * @return PreparedStatement
     */
    public function prepare(Connection $connection, array $paramTypes = [], array $resultTypes = []): PreparedStatement
    {
        $mergedTypes = $this->mergeParameterTypes($paramTypes);
        $hasUnknown  = \in_array(null, $mergedTypes, true);
        $autoFetch   = PreparedStatement::getAutoFetchParameterTypes();

        try {
            PreparedStatement::setAutoFetchParameterTypes($hasUnknown);
            $this->preparedStatement = $connection->prepare($this->sql, $mergedTypes, $resultTypes);

            if (!$hasUnknown) {
                $this->preparedStatement->setNumberOfParameters(\count($this->parameterTypes));
            }

            return $this->preparedStatement;
        } finally {
            PreparedStatement::setAutoFetchParameterTypes($autoFetch);
        }
    }

    /**
     * Executes the prepared statement using only the given parameters (requires prepare() to be called first)
     *
     * @param array $params Parameters (keys are treated as names unless $namedParameterMap is empty)
     * @return Result
     * @throws exceptions\RuntimeException
     * @throws ServerException
     */
    public function executePrepared(array $params = []): Result
    {
        if (null === $this->preparedStatement) {
            throw new exceptions\RuntimeException(__METHOD__ . '(): prepare() should be called first');
        }
        return $this->preparedStatement->executeParams(
            [] === $this->namedParameterMap ? $params : $this->mapNamedParameters($params)
        );
    }
}
