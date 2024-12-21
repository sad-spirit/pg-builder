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

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\{
    CommonTableExpression,
    GenericNode,
    WithClause
};

/**
 * Base class for Nodes representing complete SQL statements
 *
 * @psalm-property WithClause $with
 *
 * @property WithClause|CommonTableExpression[] $with
 */
abstract class Statement extends GenericNode
{
    /** @var WithClause */
    protected $p_with;

    /**
     * Parser instance, used when adding nodes to a statement as SQL strings
     * @var Parser|null
     */
    private $parser;

    public function __construct()
    {
        $this->generatePropertyNames();

        $this->p_with = new WithClause();
        $this->p_with->parentNode = $this;
    }

    public function setWith(WithClause $with): void
    {
        $this->setRequiredProperty($this->p_with, $with);
    }

    /**
     * Sets the parser instance to use
     * @param Parser $parser
     */
    public function setParser(Parser $parser): void
    {
        $this->parser = $parser;
    }

    /**
     * Returns the parser
     * @return Parser|null
     */
    public function getParser(): ?Parser
    {
        if (
            null === $this->parser && null !== $this->parentNode
            && null !== ($parser = $this->parentNode->getParser())
        ) {
            $this->setParser($parser);
        }
        return $this->parser;
    }

    public function setParentNode(Node $parent = null): void
    {
        parent::setParentNode($parent);
        if (
            null === $this->parser && null !== $this->parentNode
            && null !== ($parser = $this->parentNode->getParser())
        ) {
            $this->setParser($parser);
        }
    }
}
