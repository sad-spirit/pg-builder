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
 * @copyright 2014-2022 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\json;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\ScalarExpression;

/**
 * Contains a helper method for JSON expressions having "ON EMPTY" / "ON ERROR" behaviours
 *
 * @psalm-require-extends GenericNode
 */
trait HasBehaviours
{
    /**
     * Sets the value for "ON EMPTY" / "ON ERROR" behaviour clause
     *
     * @param string|ScalarExpression|null $property
     * @param string                       $clauseName Name of the clause (for exception messages only)
     * @param array                        $allowed
     * @param string|ScalarExpression|null $value
     * @return void
     */
    final protected function setBehaviour(&$property, string $clauseName, array $allowed, $value): void
    {
        if (null !== $value) {
            if (!is_string($value) && !($value instanceof ScalarExpression)) {
                throw new InvalidArgumentException(sprintf(
                    "Either a string or an instance of ScalarExpression expected for %s clause, %s given",
                    $clauseName,
                    is_object($value) ? 'object(' . get_class($value) . ')' : gettype($value)
                ));
            } elseif (is_string($value) && !in_array($value, $allowed)) {
                throw new InvalidArgumentException(sprintf(
                    "Unrecognized value '%s' for %s clause, expected one of '%s'",
                    $value,
                    $clauseName,
                    implode("', '", $allowed)
                ));
            }
        }

        if (!is_string($property) && !is_string($value)) {
            $this->setProperty($property, $value);
            return;
        }
        if ($property instanceof ScalarExpression) {
            $property->setParentNode(null);
        }
        if ($value instanceof ScalarExpression) {
            $value->setParentNode($this);
        }
        $property = $value;
    }
}
