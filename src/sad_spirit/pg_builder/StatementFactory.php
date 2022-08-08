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
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\{
    MultipleSetClause,
    QualifiedName,
    ScalarExpression,
    SetToDefault,
    SingleSetClause,
    TargetElement,
    expressions\RowExpression,
    lists\RowList,
    lists\SetClauseList,
    lists\TargetList,
    range\FromElement,
    range\InsertTarget,
    range\UpdateOrDeleteTarget
};
use sad_spirit\pg_wrapper\Connection;

/**
 * Helper class for creating statements and passing Parser object to them
 */
class StatementFactory
{
    /**
     * Query parser, will be passed to created statements
     * @var Parser
     */
    private $parser;

    /**
     * Query builder
     * @var StatementToStringWalker
     */
    private $builder;

    /**
     * Whether to generate SQL suitable for PDO
     * @var bool
     */
    private $PDOCompatible;

    /**
     * Constructor, sets objects to parse and build SQL
     *
     * @param Parser|null                  $parser        A Parser instance with default settings will be used
     *                                                    if not given
     * @param StatementToStringWalker|null $builder       Usually an instance of SqlBuilderWalker.
     *                                                    An instance of SqlBuilderWalker with default settings
     *                                                    will be used if not given.
     * @param bool                         $PDOCompatible Whether to generate SQL suitable for PDO rather than
     *                                                    for native pgsql extension: named parameters
     *                                                    will not be replaced by positional ones
     */
    public function __construct(
        Parser $parser = null,
        StatementToStringWalker $builder = null,
        bool $PDOCompatible = false
    ) {
        $this->parser        = $parser ?? new Parser(new Lexer());
        $this->builder       = $builder ?? new SqlBuilderWalker();
        $this->PDOCompatible = $PDOCompatible;
    }

    /**
     * Creates an instance of StatementFactory based on properties of native DB connection
     *
     * @param Connection $connection If Connection has a DB metadata cache object, that cache will also be used
     *                               in Parser for storing ASTs
     * @return self
     */
    public static function forConnection(Connection $connection): self
    {
        $serverVersion = pg_parameter_status($connection->getResource(), 'server_version');
        if (version_compare($serverVersion, '9.5', '<')) {
            throw new exceptions\RuntimeException(
                'PostgreSQL versions earlier than 9.5 are no longer supported, '
                . 'connected server reports version ' . $serverVersion
            );
        }

        $column       = $connection->execute('show standard_conforming_strings')->fetchColumn(0);
        $lexerOptions = ['standard_conforming_strings' => 'on' === $column[0]];

        $clientEncoding = pg_parameter_status($connection->getResource(), 'client_encoding');
        $builderOptions = ['escape_unicode' => 'UTF8' !== $clientEncoding];

        return new self(
            new Parser(new Lexer($lexerOptions), $connection->getMetadataCache()),
            new SqlBuilderWalker($builderOptions)
        );
    }

    /**
     * Creates an instance of StatementFactory based on properties of PDO connection object
     *
     * @param \PDO $pdo
     * @return self
     */
    public static function forPDO(\PDO $pdo): self
    {
        // obligatory sanity check
        if ('pgsql' !== ($driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME))) {
            throw new exceptions\InvalidArgumentException(
                'Connection to PostgreSQL server expected, given PDO object reports ' . $driver . ' driver'
            );
        }
        if (version_compare($version = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION), '9.5', '<')) {
            throw new exceptions\RuntimeException(
                'PostgreSQL versions earlier than 9.5 are no longer supported, '
                . 'connected server reports version ' . $version
            );
        }

        $standard     = $pdo->query('show standard_conforming_strings')->fetchColumn(0);
        $lexerOptions = ['standard_conforming_strings' => 'on' === $standard];

        $serverInfo     = $pdo->getAttribute(\PDO::ATTR_SERVER_INFO);
        $builderOptions = ['escape_unicode' => false === strpos($serverInfo, 'Client Encoding: UTF8')];

        return new self(new Parser(new Lexer($lexerOptions)), new SqlBuilderWalker($builderOptions), true);
    }

    /**
     * Returns the Parser for converting SQL fragments to ASTs
     *
     * @return Parser
     */
    public function getParser(): Parser
    {
        return $this->parser;
    }

    /**
     * Returns the SQL builder object
     *
     * @return StatementToStringWalker
     */
    public function getBuilder(): StatementToStringWalker
    {
        return $this->builder;
    }

    /**
     * Creates an AST representing a complete statement from SQL string
     *
     * @param string $sql
     * @return Statement
     * @throws exceptions\SyntaxException
     */
    public function createFromString(string $sql): Statement
    {
        $parser = $this->getParser();
        $stmt   = $parser->parseStatement($sql);
        $stmt->setParser($parser);

        return $stmt;
    }

    /**
     * Creates an object containing SQL statement string and parameter mappings from AST
     *
     * @param Statement $ast
     * @return NativeStatement
     */
    public function createFromAST(Statement $ast): NativeStatement
    {
        $pw = new ParameterWalker($this->PDOCompatible);
        $ast->dispatch($pw);

        $builder = $this->getBuilder();
        $builder->enablePDOPrepareCompatibility($this->PDOCompatible && [] !== $pw->getParameterTypes());

        return new NativeStatement($ast->dispatch($builder), $pw->getParameterTypes(), $pw->getNamedParameterMap());
    }

    /**
     * Creates a DELETE statement object
     *
     * @param string|UpdateOrDeleteTarget $from
     * @return Delete
     */
    public function delete($from): Delete
    {
        if ($from instanceof UpdateOrDeleteTarget) {
            $relation = $from;
        } else {
            $relation = $this->getParser()->parseRelationExpressionOptAlias($from);
        }

        $delete = new Delete($relation);
        $delete->setParser($this->getParser());

        return $delete;
    }

    /**
     * Creates an INSERT statement object
     *
     * @param string|QualifiedName|InsertTarget $into
     * @return Insert
     */
    public function insert($into): Insert
    {
        if ($into instanceof InsertTarget) {
            $relation = $into;
        } elseif ($into instanceof QualifiedName) {
            $relation = new InsertTarget($into);
        } else {
            $relation = $this->getParser()->parseInsertTarget($into);
        }

        $insert = new Insert($relation);
        $insert->setParser($this->getParser());

        return $insert;
    }

    /**
     * Creates a MERGE statement object
     *
     * @param UpdateOrDeleteTarget|string $into
     * @param FromElement|string          $using
     * @param ScalarExpression|string     $on
     * @return Merge
     */
    public function merge($into, $using, $on): Merge
    {
        if ($into instanceof UpdateOrDeleteTarget) {
            $relation = $into;
        } else {
            $relation = $this->getParser()->parseRelationExpressionOptAlias($into);
        }
        if ($using instanceof FromElement) {
            $joined = $using;
        } else {
            $joined = $this->getParser()->parseFromElement($using);
        }
        if ($on instanceof ScalarExpression) {
            $condition = $on;
        } else {
            $condition = $this->getParser()->parseExpression($on);
        }

        $merge = new Merge($relation, $joined, $condition);
        $merge->setParser($this->getParser());

        return $merge;
    }

    /**
     * Creates a SELECT statement object
     *
     * @param string|iterable<TargetElement|string>    $list
     * @param string|iterable<FromElement|string>|null $from
     * @return Select
     */
    public function select($list, $from = null): Select
    {
        if ($list instanceof TargetList) {
            $targetList = $list;
        } elseif (is_string($list)) {
            $targetList = TargetList::createFromString($this->getParser(), $list);
        } else {
            // we don't pass $list since it may contain strings instead of TargetElements,
            // Parser may be needed for that
            $targetList = new TargetList();
        }

        $select = new Select($targetList);
        $select->setParser($this->getParser());

        if (!is_string($list) && !($list instanceof TargetList)) {
            $select->list->replace($list);
        }
        if (null !== $from) {
            $select->from->replace($from);
        }

        return $select;
    }

    /**
     * Creates an UPDATE statement object
     *
     * @param string|UpdateOrDeleteTarget                               $table
     * @param string|iterable<SingleSetClause|MultipleSetClause|string> $set
     * @return Update
     */
    public function update($table, $set): Update
    {
        if ($table instanceof UpdateOrDeleteTarget) {
            $relation = $table;
        } else {
            $relation = $this->getParser()->parseRelationExpressionOptAlias($table);
        }

        if ($set instanceof SetClauseList) {
            $setList = $set;
        } elseif (is_string($set)) {
            $setList = $this->getParser()->parseSetClauseList($set);
        } else {
            // we don't pass $set since it may contain strings instead of SetTargetElements,
            // Parser may be needed for that
            $setList = new SetClauseList();
        }

        $update = new Update($relation, $setList);
        $update->setParser($this->getParser());

        if (!is_string($set) && !($set instanceof SetClauseList)) {
            $update->set->replace($set);
        }

        return $update;
    }

    /**
     * Creates a VALUES statement object
     *
     * Take care when passing arrays/iterators here. As VALUES statement may contain several rows, an outer array
     * will be for rows rather than for elements within row, thus
     * <code>$factory->values([new StringConstant('foo'), new StringConstant('bar')]);</code>
     * will fail,
     * <code>$factory->values([[new StringConstant('foo'), new StringConstant('bar')]]);</code>
     * will produce a VALUES statement with one row containing two elements, and
     * <code>$factory->values([[new StringConstant('foo')], [new StringConstant('bar')]]);</code>
     * will produce a VALUES statement with two rows, each having a single element.
     *
     * @param string|iterable<RowExpression|string|iterable<ScalarExpression|SetToDefault|string>> $rows
     * @return Values
     */
    public function values($rows): Values
    {
        if ($rows instanceof RowList) {
            $rowList = $rows;
        } elseif (is_string($rows)) {
            $rowList = RowList::createFromString($this->getParser(), $rows);
        } else {
            // we don't pass $rows as it may contain strings/arrays instead of RowExpressions,
            // Parser may be needed for that
            $rowList = new RowList();
        }

        $values = new Values($rowList);
        $values->setParser($this->getParser());

        if (!is_string($rows) && !($rows instanceof RowList)) {
            $values->rows->replace($rows);
        }

        return $values;
    }
}
