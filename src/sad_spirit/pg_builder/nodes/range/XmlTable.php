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

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\nodes\{
    ScalarExpression,
    xml\XmlColumnList,
    xml\XmlNamespaceList
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing an XMLTABLE clause in FROM
 *
 * @property bool             $lateral
 * @property ScalarExpression $rowExpression
 * @property ScalarExpression $documentExpression
 * @property XmlColumnList    $columns
 * @property XmlNamespaceList $namespaces
 */
class XmlTable extends FromElement
{
    public function __construct(
        ScalarExpression $rowExpression,
        ScalarExpression $documentExpression,
        XmlColumnList $columns,
        XmlNamespaceList $namespaces = null
    ) {
        $this->props['lateral'] = false;

        $this->setRowExpression($rowExpression);
        $this->setDocumentExpression($documentExpression);
        $this->setNamedProperty('columns', $columns);
        $this->setNamedProperty('namespaces', $namespaces ?? new XmlNamespaceList([]));
    }

    public function setRowExpression(ScalarExpression $rowExpression): void
    {
        $this->setNamedProperty('rowExpression', $rowExpression);
    }

    public function setDocumentExpression(ScalarExpression $documentExpression): void
    {
        $this->setNamedProperty('documentExpression', $documentExpression);
    }

    public function setLateral(bool $lateral): void
    {
        $this->props['lateral'] = $lateral;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkXmlTable($this);
    }
}
