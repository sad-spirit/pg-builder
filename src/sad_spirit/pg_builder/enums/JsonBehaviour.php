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

namespace sad_spirit\pg_builder\enums;

use sad_spirit\pg_builder\Node;
use sad_spirit\pg_builder\nodes\json\{
    JsonExists,
    JsonQuery,
    JsonValue
};
use sad_spirit\pg_builder\nodes\range\{
    JsonTable,
    json\JsonExistsColumnDefinition,
    json\JsonRegularColumnDefinition
};

/**
 * Contains possible behaviours for `ON ERROR` and `ON EMPTY` clauses of various JSON statements
 *
 * This is based on `JsonBehaviorType` from `src/include/nodes/primnodes.h`. The `::DEFAULT` case is never actually
 * set for a behaviour (an implementation of `ScalarExpression` will be set instead), but used for checks.
 * The `::EMPTY` case is omitted as it is not actually used in `gram.y`
 */
enum JsonBehaviour: string
{
    use CreateFromKeywords;

    case NULL         = 'null';
    case ERROR        = 'error';
    case TRUE         = 'true';
    case FALSE        = 'false';
    case UNKNOWN      = 'unknown';
    case EMPTY_ARRAY  = 'empty array';
    case EMPTY_OBJECT = 'empty object';
    case DEFAULT      = 'default';

    /**
     * Contains mapping from class names to possible behaviours for `ON ERROR` clause
     */
    private const ON_ERROR = [
        JsonExists::class => [
            self::ERROR,
            self::TRUE,
            self::FALSE,
            self::UNKNOWN
        ],
        JsonQuery::class => [
            self::NULL,
            self::ERROR,
            self::EMPTY_ARRAY,
            self::EMPTY_OBJECT,
            self::DEFAULT
        ],
        JsonTable::class => [
            self::ERROR,
            self::EMPTY_ARRAY
        ],
        JsonValue::class => [
            self::NULL,
            self::ERROR,
            self::DEFAULT
        ],
        JsonExistsColumnDefinition::class => [
            self::ERROR,
            self::TRUE,
            self::FALSE,
            self::UNKNOWN
        ],
        JsonRegularColumnDefinition::class => [
            self::NULL,
            self::ERROR,
            self::EMPTY_ARRAY,
            self::EMPTY_OBJECT,
            self::DEFAULT
        ]
    ];

    /**
     * Contains mapping from class names to possible behaviours for `ON EMPTY` clause
     */
    private const ON_EMPTY = [
        JsonQuery::class => [
            self::NULL,
            self::ERROR,
            self::EMPTY_ARRAY,
            self::EMPTY_OBJECT,
            self::DEFAULT
        ],
        JsonValue::class => [
            self::NULL,
            self::ERROR,
            self::DEFAULT
        ],
        JsonRegularColumnDefinition::class => [
            self::NULL,
            self::ERROR,
            self::EMPTY_ARRAY,
            self::EMPTY_OBJECT,
            self::DEFAULT
        ]
    ];

    public function nameForExceptionMessage(): string
    {
        return match ($this) {
            self::EMPTY_ARRAY => 'EMPTY [ARRAY]',
            default => \strtoupper($this->value)
        };
    }

    /**
     * Returns possible behaviours for `ON ERROR` clause for the given class name
     *
     * @param class-string<Node> $className
     * @return JsonBehaviour[]
     */
    public static function casesForOnErrorClause(string $className): array
    {
        return self::ON_ERROR[$className] ?? [];
    }

    /**
     * Returns possible behaviours for `ON EMPTY` clause for the given class name
     *
     * @param class-string<Node> $className
     * @return JsonBehaviour[]
     */
    public static function casesForOnEmptyClause(string $className): array
    {
        return self::ON_EMPTY[$className] ?? [];
    }
}
