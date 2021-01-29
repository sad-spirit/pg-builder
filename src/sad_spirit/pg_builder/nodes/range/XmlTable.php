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
    /** @var bool */
    protected $p_lateral;
    /** @var ScalarExpression */
    protected $p_rowExpression;
    /** @var ScalarExpression */
    protected $p_documentExpression;
    /** @var XmlColumnList */
    protected $p_columns;
    /** @var XmlNamespaceList */
    protected $p_namespaces;

    public function __construct(
        ScalarExpression $rowExpression,
        ScalarExpression $documentExpression,
        XmlColumnList $columns,
        XmlNamespaceList $namespaces = null
    ) {
        $this->generatePropertyNames();

        $this->p_lateral = false;

        $this->setRowExpression($rowExpression);
        $this->setDocumentExpression($documentExpression);
        $this->setProperty($this->p_columns, $columns);
        $this->setProperty($this->p_namespaces, $namespaces ?? new XmlNamespaceList([]));
    }

    public function setRowExpression(ScalarExpression $rowExpression): void
    {
        $this->setProperty($this->p_rowExpression, $rowExpression);
    }

    public function setDocumentExpression(ScalarExpression $documentExpression): void
    {
        $this->setProperty($this->p_documentExpression, $documentExpression);
    }

    public function setLateral(bool $lateral): void
    {
        $this->p_lateral = $lateral;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkXmlTable($this);
    }
}
