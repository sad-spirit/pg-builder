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
