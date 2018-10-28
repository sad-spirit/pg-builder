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
 * @copyright 2014-2018 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\WithClause;

/**
 * Base class for Nodes representing complete SQL statements
 *
 * @property WithClause $with
 */
abstract class Statement extends Node
{
    /**
     * Parser instance, used when adding nodes to a statement as SQL strings
     * @var Parser
     */
    private $_parser;

    public function __construct()
    {
        $this->props['with'] = new WithClause(array());
        $this->props['with']->setParentNode($this);
    }

    public function setWith(WithClause $with = null)
    {
        $this->setNamedProperty('with', $with);
    }

    /**
     * Sets the parser instance to use
     * @param Parser $parser
     */
    public function setParser(Parser $parser)
    {
        $this->_parser = $parser;
    }

    /**
     * Returns the parser
     * @return Parser|null
     */
    protected function getParser()
    {
        if (!$this->_parser && $this->parentNode && ($parser = $this->parentNode->getParser())) {
            $this->setParser($parser);
        }
        return $this->_parser;
    }

    protected function setParentNode(Node $parent = null)
    {
        parent::setParentNode($parent);
        if (!$this->_parser && $this->parentNode && ($parser = $this->parentNode->getParser())) {
            $this->setParser($parser);
        }
    }
}
