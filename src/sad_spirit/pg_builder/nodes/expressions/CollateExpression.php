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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\{
    nodes\GenericNode,
    nodes\QualifiedName,
    nodes\ScalarExpression,
    TreeWalker
};

/**
 * AST node representing a "foo COLLATE bar" expression
 *
 * @property      ScalarExpression $argument
 * @property-read QualifiedName    $collation
 */
class CollateExpression extends GenericNode implements ScalarExpression
{
    public function __construct(protected ScalarExpression $p_argument, protected QualifiedName $p_collation)
    {
        $this->generatePropertyNames();
        $this->p_argument->setParentNode($this);
        $this->p_collation->setParentNode($this);
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkCollateExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_COLLATE;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_LEFT;
    }
}
