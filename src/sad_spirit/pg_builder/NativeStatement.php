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

namespace sad_spirit\pg_builder;

use sad_spirit\pg_wrapper\Connection;
use sad_spirit\pg_wrapper\PreparedStatement;
use sad_spirit\pg_wrapper\ResultSet;
use sad_spirit\pg_wrapper\exceptions\ServerException;

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
     * @var array
     */
    private $namedParameterMap;

    /**
     * Type info: 'parameter position' => 'parameter type' if explicit typecasts were used for parameters
     * @var array
     */
    private $parameterTypes;

    /**
     * @var PreparedStatement
     */
    private $preparedStatement;

    /**
     * Constructor, sets the query building results
     *
     * @param string $sql
     * @param array $parameterTypes
     * @param array $namedParameterMap
     */
    public function __construct($sql, array $parameterTypes, array $namedParameterMap)
    {
        $this->sql               = $sql;
        $this->parameterTypes    = $parameterTypes;
        $this->namedParameterMap = $namedParameterMap;
    }

    /**
     * Prevents serialization of $_prepared property
     */
    public function __sleep()
    {
        return ['sql', 'parameterTypes', 'namedParameterMap'];
    }

    /**
     * Returns the SQL statement string
     *
     * @return string
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * Returns mapping from named parameters to positional ones
     *
     * @return array
     */
    public function getNamedParameterMap()
    {
        return $this->namedParameterMap;
    }

    /**
     * Returns known types for parameters (if parameters were used with type casts)
     *
     * @return array
     */
    public function getParameterTypes()
    {
        return $this->parameterTypes;
    }

    /**
     * Converts parameters array keyed with parameters' names to positional array
     *
     * @param array $parameters
     * @return array
     * @throws exceptions\InvalidArgumentException
     */
    public function mapNamedParameters(array $parameters)
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
     * @param array $inputTypes Parameter types (keys can be either names or positions), types from this
     *                          array take precedence over types from parameterTypes
     * @return array
     * @throws exceptions\InvalidArgumentException
     */
    public function mergeInputTypes(array $inputTypes)
    {
        $types = $this->parameterTypes;
        foreach ($inputTypes as $key => $type) {
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
     * @param array      $params      Parameters (keys are treated as names unless namedParameterMap is empty)
     * @param array      $inputTypes  Parameter types (keys can be either names or positions), types from this
     *                                array take precedence over types from parameterTypes
     * @param array      $outputTypes Result types to pass to ResultSet (keys can be either names or positions)
     * @return bool|ResultSet|int
     * @throws ServerException
     * @throws exceptions\InvalidArgumentException
     */
    public function executeParams(
        Connection $connection,
        array $params,
        array $inputTypes = [],
        array $outputTypes = []
    ) {
        if (empty($this->namedParameterMap)) {
            return $connection->executeParams(
                $this->sql,
                $params,
                $this->mergeInputTypes($inputTypes),
                $outputTypes
            );
        } else {
            return $connection->executeParams(
                $this->sql,
                $this->mapNamedParameters($params),
                $this->mergeInputTypes($inputTypes),
                $outputTypes
            );
        }
    }

    /**
     * Prepares the query for execution.
     *
     * @param Connection $connection DB connection
     * @param array      $types      Parameter types (keys can be either names or positions), types from this
     *                               array take precedence over types from parameterTypes
     * @return PreparedStatement
     */
    public function prepare(Connection $connection, array $types = [])
    {
        $this->preparedStatement = $connection->prepare($this->sql, $this->mergeInputTypes($types));
        return $this->preparedStatement;
    }

    /**
     * Executes the prepared statement (requires prepare() to be called first)
     *
     * @param array $params
     * @param array $resultTypes
     * @return bool|ResultSet|int
     * @throws exceptions\RuntimeException
     * @throws ServerException
     */
    public function executePrepared(array $params = [], array $resultTypes = [])
    {
        if (!$this->preparedStatement) {
            throw new exceptions\RuntimeException(__METHOD__ . '(): prepare() should be called first');
        }
        return $this->preparedStatement->execute($this->mapNamedParameters($params), $resultTypes);
    }
}
