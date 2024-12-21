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

use sad_spirit\pg_builder\NativeStatement;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Contains a method for converting query parameters
 *
 * Implemented as a trait for the time being, until {@see ParserAwareTypeConverterFactory} is removed
 *
 * @psalm-require-implements TypeNameNodeHandler
 */
trait ParametersConverter
{
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
