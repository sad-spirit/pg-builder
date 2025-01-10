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

    protected ?Identifier $p_alias = null;

    /**
     * Constructor
     *
     * @param iterable<Identifier|string>|null $list
     * @param Identifier|string|null           $alias
     */
    public function __construct($list = null, Identifier|string|null $alias = null)
    {
        $this->generatePropertyNames();
        parent::__construct($list);
        $this->setAlias($alias);
    }

    /**
     * Sets the table alias for the join columns in USING clause
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
