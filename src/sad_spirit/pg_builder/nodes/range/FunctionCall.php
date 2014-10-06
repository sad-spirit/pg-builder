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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\nodes\FunctionCall as BaseFunctionCall,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\nodes\Identifier,
    sad_spirit\pg_builder\nodes\lists\IdentifierList,
    sad_spirit\pg_builder\nodes\lists\ColumnDefinitionList,
    sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing a function call in FROM clause
 *
 * @property-read IdentifierList|ColumnDefinitionList $columnAliases
 * @property-read BaseFunctionCall                    $function
 * @property      bool                                $lateral
 * @property      bool                                $withOrdinality
 */
class FunctionCall extends FromElement
{
    public function __construct(BaseFunctionCall $function)
    {
        $this->setNamedProperty('function', $function);
        $this->props['lateral']        = false;
        $this->props['withOrdinality'] = false;
    }

    public function setLateral($lateral)
    {
        $this->props['lateral'] = (bool)$lateral;
    }

    public function setWithOrdinality($ordinality)
    {
        $this->props['withOrdinality'] = (bool)$ordinality;
    }

    public function setAlias(Identifier $tableAlias = null, $columnAliases = null)
    {
        if (null === $columnAliases || $columnAliases instanceof IdentifierList) {
            parent::setAlias($tableAlias, $columnAliases);

        } elseif ($columnAliases instanceof ColumnDefinitionList) {
            parent::setAlias($tableAlias, null);
            $this->setNamedProperty('columnAliases', $columnAliases);

        } else {
            throw new InvalidArgumentException(sprintf(
                '%s expects an instance of either IdentifierList or ColumnDefinitionList, %s given',
                __METHOD__, is_object($columnAliases) ? 'object(' . get_class($columnAliases) . ')' : gettype($columnAliases)
            ));
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkRangeFunctionCall($this);
    }
}