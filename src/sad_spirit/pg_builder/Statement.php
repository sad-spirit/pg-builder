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

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\{
    GenericNode,
    WithClause
};

/**
 * Base class for Nodes representing complete SQL statements
 *
 * @property WithClause $with
 */
abstract class Statement extends GenericNode
{
    protected WithClause $p_with;
    /** Parser instance, used when adding nodes to a statement as SQL strings */
    private ?Parser $parser = null;

    public function __construct()
    {
        $this->generatePropertyNames();

        $this->p_with = new WithClause();
        $this->p_with->parentNode = \WeakReference::create($this);
    }

    public function setWith(WithClause $with): void
    {
        $this->setRequiredProperty($this->p_with, $with);
    }

    /**
     * Sets the parser instance to use
     */
    public function setParser(Parser $parser): void
    {
        $this->parser = $parser;
    }

    /**
     * Returns the parser
     */
    public function getParser(): ?Parser
    {
        if (
            null === $this->parser
            && null !== ($parentNode = $this->getParentNode())
            && null !== ($parser = $parentNode->getParser())
        ) {
            $this->setParser($parser);
        }
        return $this->parser;
    }

    public function setParentNode(?Node $parent): void
    {
        parent::setParentNode($parent);
        if (
            null === $this->parser
            && null !== ($parentNode = $this->getParentNode())
            && null !== ($parser = $parentNode->getParser())
        ) {
            $this->setParser($parser);
        }
    }
}
