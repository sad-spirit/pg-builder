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

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\{
    Parseable,
    Parser,
    TreeWalker
};
use sad_spirit\pg_builder\nodes\{
    HasBothPropsAndOffsets,
    Identifier,
    lists\IdentifierList
};

/**
 * AST Node for USING clause in JOIN expression
 *
 * @property Identifier|null $alias
 */
class UsingClause extends IdentifierList implements Parseable
{
    use HasBothPropsAndOffsets;

    /** @internal Maps to `$alias` magic property, use the latter instead */
    protected ?Identifier $p_alias = null;

    /**
     * Constructor
     *
     * @param iterable<Identifier|string>|null $list
     */
    public function __construct($list = null, Identifier|string|null $alias = null)
    {
        $this->generatePropertyNames();
        parent::__construct($list);
        $this->setAlias($alias);
    }

    /**
     * Sets the table alias for the join columns in USING clause
     *
     * @internal Support method for `$alias` magic property, use the property instead
     */
    public function setAlias(Identifier|string|null $alias): void
    {
        $this->setProperty(
            $this->p_alias,
            null === $alias || $alias instanceof Identifier ? $alias : new Identifier($alias)
        );
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkUsingClause($this);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseUsingClause($sql);
    }
}
