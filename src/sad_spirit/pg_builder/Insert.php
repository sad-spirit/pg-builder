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

declare(strict_types=1);

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\{
    lists\TargetList,
    lists\SetTargetList,
    range\InsertTarget,
    OnConflictClause
};
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * AST node representing INSERT statement
 *
 * @property-read InsertTarget          $relation
 * @property      SetTargetList         $cols
 * @property      SelectCommon|null     $values
 * @property      string|null           $overriding
 * @property      OnConflictClause|null $onConflict
 * @property      TargetList            $returning
 */
class Insert extends Statement
{
    public const OVERRIDING_USER   = 'user';
    public const OVERRIDING_SYSTEM = 'system';

    private const ALLOWED_OVERRIDING = [
        self::OVERRIDING_SYSTEM => true,
        self::OVERRIDING_USER   => true
    ];

    public function __construct(InsertTarget $relation)
    {
        parent::__construct();

        $this->setNamedProperty('relation', $relation);
        $this->setNamedProperty('cols', new SetTargetList());
        $this->setNamedProperty('returning', new TargetList());
        $this->props = array_merge($this->props, [
            'values'     => null,
            'onConflict' => null,
            'overriding' => null
        ]);
    }

    public function setValues(SelectCommon $values = null): void
    {
        $this->setNamedProperty('values', $values);
    }

    public function setOnConflict($onConflict = null): void
    {
        if (is_string($onConflict)) {
            $onConflict = $this->getParserOrFail('ON CONFLICT clause')->parseOnConflict($onConflict);
        }
        if (null !== $onConflict && !($onConflict instanceof OnConflictClause)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects an instance of OnConflictClause, %s given',
                __METHOD__,
                is_object($onConflict) ? 'object(' . get_class($onConflict) . ')' : gettype($onConflict)
            ));
        }
        $this->setNamedProperty('onConflict', $onConflict);
    }

    public function setOverriding(?string $overriding = null): void
    {
        if (null !== $overriding && !isset(self::ALLOWED_OVERRIDING[$overriding])) {
            throw new InvalidArgumentException("Unknown override kind '{$overriding}'");
        }
        $this->props['overriding'] = $overriding;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkInsertStatement($this);
    }
}
