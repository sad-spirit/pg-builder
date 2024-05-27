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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing "foo ... IS [NOT] JSON ..." expression with all bells and whistles
 *
 * Cannot be represented by IsExpression due to aforementioned bells and whistles
 *
 * @property ScalarExpression $argument
 * @property ?string          $type
 * @property ?bool            $uniqueKeys
 */
class IsJsonExpression extends NegatableExpression
{
    public const TYPE_VALUE  = 'value';
    public const TYPE_ARRAY  = 'array';
    public const TYPE_OBJECT = 'object';
    public const TYPE_SCALAR = 'scalar';

    public const TYPES = [
        self::TYPE_VALUE,
        self::TYPE_ARRAY,
        self::TYPE_OBJECT,
        self::TYPE_SCALAR
    ];

    /** @var ScalarExpression */
    protected $p_argument;
    /** @var string|null */
    protected $p_type;
    /** @var bool|null */
    protected $p_uniqueKeys;

    public function __construct(
        ScalarExpression $argument,
        bool $not = false,
        ?string $type = null,
        ?bool $unique = null
    ) {
        $this->generatePropertyNames();

        $this->setType($type);

        $this->p_argument   = $argument;
        $this->p_argument->setParentNode($this);
        $this->p_not        = $not;
        $this->p_uniqueKeys = $unique;
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function setType(?string $type): void
    {
        if (null !== $type && !in_array($type, self::TYPES)) {
            throw new InvalidArgumentException("Unrecognized JSON type '$type'");
        }
        $this->p_type = $type;
    }

    public function setUniqueKeys(?bool $unique): void
    {
        $this->p_uniqueKeys = $unique;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkIsJsonExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_IS;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_NONE;
    }
}
