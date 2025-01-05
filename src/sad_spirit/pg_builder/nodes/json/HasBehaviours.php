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

namespace sad_spirit\pg_builder\nodes\json;

use sad_spirit\pg_builder\enums\JsonBehaviour;
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
     * @param JsonBehaviour|ScalarExpression|null $property
     * @param bool                                $onEmpty
     * @param JsonBehaviour|ScalarExpression|null $value
     * @return void
     */
    final protected function setBehaviour(
        JsonBehaviour|ScalarExpression|null &$property,
        bool $onEmpty,
        JsonBehaviour|ScalarExpression|null $value
    ): void {
        if (null !== $value) {
            $checkCase  = $value instanceof JsonBehaviour ? $value : JsonBehaviour::DEFAULT;
            $applicable = $onEmpty
                ? JsonBehaviour::casesForOnEmptyClause($this::class)
                : JsonBehaviour::casesForOnErrorClause($this::class);
            if (!\in_array($checkCase, $applicable, true)) {
                throw new InvalidArgumentException(\sprintf(
                    "Invalid %s behaviour for %s clause of %s. Valid ones are: %s",
                    $checkCase->nameForExceptionMessage(),
                    $onEmpty ? 'ON EMPTY' : 'ON ERROR',
                    $this::class,
                    \implode(', ', \array_map(
                        fn (JsonBehaviour $behaviour) => $behaviour->nameForExceptionMessage(),
                        $applicable
                    ))
                ));
            }
        }

        if (!$property instanceof JsonBehaviour && !$value instanceof JsonBehaviour) {
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
