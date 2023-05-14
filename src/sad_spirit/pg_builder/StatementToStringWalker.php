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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder;

/**
 * Interface for TreeWalkers building SQL
 *
 * Adds explicit string return type hints to methods accepting Statement instances, so that
 * StatementFactory::createFromAST() will work as expected
 */
interface StatementToStringWalker extends TreeWalker
{
    /**
     * Whether to generate SQL that will be compatible with PDO::prepare()
     *
     * This should do two things
     *  - prevent generating of dollar-quoted strings PDO cannot parse properly
     *  - escape question marks in operators so that PDO will not treat them as placeholders (requires PHP 7.4)
     *    https://wiki.php.net/rfc/pdo_escape_placeholders
     *
     * This switch is defined here rather than implemented as an option for SqlBuilderWalker because it should
     * be toggled on only when we know that the statement actually *has* placeholders, in other words,
     * only after running ParameterWalker in StatementFactory::createFromAST()
     *
     * @param bool $enable
     */
    public function enablePDOPrepareCompatibility(bool $enable): void;

    /**
     * {@inheritDoc}
     * @return string Generated SQL for SELECT statement
     */
    public function walkSelectStatement(Select $statement): string;

    /**
     * {@inheritDoc}
     * @return string Generated SQL for SELECT statements combined via set operator
     */
    public function walkSetOpSelectStatement(SetOpSelect $statement): string;

    /**
     * {@inheritDoc}
     * @return string Generated SQL for VALUES statement
     */
    public function walkValuesStatement(Values $statement): string;

    /**
     * {@inheritDoc}
     * @return string Generated SQL for DELETE statement
     */
    public function walkDeleteStatement(Delete $statement): string;

    /**
     * {@inheritDoc}
     * @return string Generated SQL for INSERT statement
     */
    public function walkInsertStatement(Insert $statement): string;

    /**
     * {@inheritDoc}
     * @return string Generated SQL for UPDATE statement
     */
    public function walkUpdateStatement(Update $statement): string;
}
