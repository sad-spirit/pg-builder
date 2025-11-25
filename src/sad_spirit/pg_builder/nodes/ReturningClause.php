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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\lists\TargetList;
use sad_spirit\pg_builder\Parser;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a RETURNING clause in Postgres 18+
 *
 * Before Postgres 18 there was no difference between this and the target list of `SELECT`,
 * since version 18 it is possible to specify aliases for `NEW` and `OLD` in `RETURNING` clause.
 *
 * @property Identifier|null $oldAlias
 * @property Identifier|null $newAlias
 *
 * @since 3.2.0
 */
class ReturningClause extends TargetList
{
    use HasBothPropsAndOffsets;

    protected ?Identifier $p_oldAlias = null;
    protected ?Identifier $p_newAlias = null;

    public function __construct(
        $list = null,
        Identifier|string|null $oldAlias = null,
        Identifier|string|null $newAlias = null
    ) {
        if ($oldAlias instanceof Identifier && $oldAlias === $newAlias) {
            throw new InvalidArgumentException("Cannot use the same Node for both aliases");
        }

        $this->generatePropertyNames();
        parent::__construct($list);
        $this->setOldAlias($oldAlias);
        $this->setNewAlias($newAlias);
    }

    public function setOldAlias(Identifier|string|null $oldAlias): void
    {
        $this->setProperty($this->p_oldAlias, \is_string($oldAlias) ? new Identifier($oldAlias) : $oldAlias);
    }

    public function setNewAlias(Identifier|string|null $newAlias): void
    {
        $this->setProperty($this->p_newAlias, \is_string($newAlias) ? new Identifier($newAlias) : $newAlias);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseReturningClause($sql);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkReturningClause($this);
    }

    public function replace($list): void
    {
        if (\is_string($list)) {
            $list = self::createFromString($this->getParserOrFail("an argument to 'replace'"), $list);
        }
        $this->setOldAlias($list instanceof self && $list->oldAlias ? clone $list->oldAlias : null);
        $this->setNewAlias($list instanceof self && $list->newAlias ? clone $list->newAlias : null);

        parent::replace($list);
    }

    public function merge(...$lists): void
    {
        $oldAlias = $this->p_oldAlias;
        $newAlias = $this->p_newAlias;
        foreach ($lists as &$list) {
            if (\is_string($list)) {
                $list = self::createFromString($this->getParserOrFail("an argument to 'merge'"), $list);
            }
            if ($list instanceof self) {
                if (null !== $list->oldAlias) {
                    if (null !== $oldAlias) {
                        throw new InvalidArgumentException('Alias for OLD cannot be specified multiple times');
                    }
                    $oldAlias = clone $list->oldAlias;
                }
                if (null !== $list->newAlias) {
                    if (null !== $newAlias) {
                        throw new InvalidArgumentException('Alias for NEW cannot be specified multiple times');
                    }
                    $newAlias = clone $list->newAlias;
                }
            }
        }
        unset($list);

        $this->setOldAlias($oldAlias);
        $this->setNewAlias($newAlias);

        parent::merge(...$lists);
    }
}
