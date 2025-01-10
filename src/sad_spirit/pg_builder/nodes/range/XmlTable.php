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

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\{
    ScalarExpression,
    xml\XmlColumnList,
    xml\XmlNamespaceList
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing an XMLTABLE clause in FROM
 *
 * @property ScalarExpression $rowExpression
 * @property ScalarExpression $documentExpression
 * @property XmlColumnList    $columns
 * @property XmlNamespaceList $namespaces
 */
class XmlTable extends LateralFromElement
{
    protected ScalarExpression $p_rowExpression;
    protected ScalarExpression $p_documentExpression;
    protected XmlNamespaceList $p_namespaces;

    public function __construct(
        ScalarExpression $rowExpression,
        ScalarExpression $documentExpression,
        protected XmlColumnList $p_columns,
        ?XmlNamespaceList $namespaces = null
    ) {
        if ($rowExpression === $documentExpression) {
            throw new InvalidArgumentException("Cannot use the same Node for row and document expressions");
        }

        $this->generatePropertyNames();

        $this->p_rowExpression = $rowExpression;
        $this->p_rowExpression->setParentNode($this);

        $this->p_documentExpression = $documentExpression;
        $this->p_documentExpression->setParentNode($this);
        $this->p_columns->setParentNode($this);

        $this->p_namespaces = $namespaces ?? new XmlNamespaceList();
        $this->p_namespaces->setParentNode($this);
    }

    public function setRowExpression(ScalarExpression $rowExpression): void
    {
        $this->setRequiredProperty($this->p_rowExpression, $rowExpression);
    }

    public function setDocumentExpression(ScalarExpression $documentExpression): void
    {
        $this->setRequiredProperty($this->p_documentExpression, $documentExpression);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkXmlTable($this);
    }
}
