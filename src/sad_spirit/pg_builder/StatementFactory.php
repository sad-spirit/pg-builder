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

namespace sad_spirit\pg_builder;

use sad_spirit\pg_wrapper\Connection;
use sad_spirit\pg_wrapper\exceptions\ServerException;

/**
 * Helper class for creating statements and passing Parser object to them
 */
class StatementFactory
{
    /**
     * Database connection
     * @var Connection|null
     */
    private $connection;

    /**
     * Query parser, will be passed to created statements
     * @var Parser|null
     */
    private $parser;

    /**
     * Query builder
     * @var TreeWalker|null
     */
    private $builder;

    /**
     * Constructor, can set the Connection and Parser objects
     *
     * @param Connection|null $connection
     * @param Parser|null $parser
     */
    public function __construct(Connection $connection = null, Parser $parser = null)
    {
        $this->connection = $connection;
        $this->parser     = $parser;
    }

    /**
     * Returns the Parser for converting SQL fragments to ASTs
     *
     * If the parser was not provided to constructor it will be created here, using
     * Connection for setting additional options if available
     *
     * @return Parser
     */
    public function getParser()
    {
        if (null === $this->parser) {
            if (null === $this->connection) {
                $cache         = null;
                $lexerOptions  = [];

            } else {
                $serverVersion = pg_parameter_status($this->connection->getResource(), 'server_version');
                if (version_compare($serverVersion, '9.5', '<')) {
                    throw new exceptions\RuntimeException(
                        'PostgreSQL versions earlier than 9.5 are no longer supported, '
                        . 'connected server reports version ' . $serverVersion
                    );
                }

                $cache = $this->connection->getMetadataCache();
                try {
                    $res = $this->connection->execute('show standard_conforming_strings');
                    $lexerOptions = [
                        'standard_conforming_strings' => 'on' === $res[0]['standard_conforming_strings']
                    ];
                } catch (ServerException $e) {
                    // the server is not aware of the setting?
                    $lexerOptions = ['standard_conforming_strings' => false];
                }
            }
            $this->parser = new Parser(new Lexer($lexerOptions), $cache);
        }
        return $this->parser;
    }

    /**
     * Sets the SQL builder object used by createFromAST()
     *
     * @param TreeWalker $builder
     */
    public function setBuilder(TreeWalker $builder)
    {
        $this->builder = $builder;
    }

    /**
     * Returns the SQL builder object
     *
     * If not explicitly set by setBuilder(), an instance of SqlBuilderWalker with default
     * options will be created.
     *
     * @return TreeWalker
     */
    public function getBuilder(): TreeWalker
    {
        if (null === $this->builder) {
            $this->builder = new SqlBuilderWalker();
        }
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
        $pw = new ParameterWalker();
        $ast->dispatch($pw);

        return new NativeStatement(
            $ast->dispatch($this->getBuilder()),
            $pw->getParameterTypes(),
            $pw->getNamedParameterMap()
        );
    }

    /**
     * Creates a DELETE statement object
     *
     * @param string|nodes\range\UpdateOrDeleteTarget $from
     * @return Delete
     */
    public function delete($from): Delete
    {
        if ($from instanceof nodes\range\UpdateOrDeleteTarget) {
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
     * @param string|nodes\QualifiedName|nodes\range\InsertTarget $into
     * @return Insert
     */
    public function insert($into): Insert
    {
        if ($into instanceof nodes\range\InsertTarget) {
            $relation = $into;
        } elseif ($into instanceof nodes\QualifiedName) {
            $relation = new nodes\range\InsertTarget($into);
        } else {
            $relation = $this->getParser()->parseInsertTarget($into);
        }

        $insert = new Insert($relation);
        $insert->setParser($this->getParser());

        return $insert;
    }

    /**
     * Creates a SELECT statement object
     *
     * @param string|array|nodes\lists\TargetList    $list
     * @param string|array|nodes\lists\FromList|null $from
     * @return Select
     */
    public function select($list, $from = null): Select
    {
        if ($list instanceof nodes\lists\TargetList) {
            $targetList = $list;
        } elseif (is_string($list)) {
            $targetList = nodes\lists\TargetList::createFromString($this->getParser(), $list);
        } else {
            // we don't pass $list since it may contain strings instead of TargetElements,
            // Parser may be needed for that
            $targetList = new nodes\lists\TargetList();
        }

        $select = new Select($targetList);
        $select->setParser($this->getParser());

        if (!is_string($list) && !($list instanceof nodes\lists\TargetList)) {
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
     * @param string|nodes\range\UpdateOrDeleteTarget $table
     * @param string|array|nodes\lists\SetClauseList  $set
     * @return Update
     */
    public function update($table, $set): Update
    {
        if ($table instanceof nodes\range\UpdateOrDeleteTarget) {
            $relation = $table;
        } else {
            $relation = $this->getParser()->parseRelationExpressionOptAlias($table);
        }

        if ($set instanceof nodes\lists\SetClauseList) {
            $setList = $set;
        } elseif (is_string($set)) {
            $setList = $this->getParser()->parseSetClauseList($set);
        } else {
            // we don't pass $set since it may contain strings instead of SetTargetElements,
            // Parser may be needed for that
            $setList = new nodes\lists\SetClauseList();
        }

        $update = new Update($relation, $setList);
        $update->setParser($this->getParser());

        if (!is_string($set) && !($set instanceof nodes\lists\SetClauseList)) {
            $update->set->replace($set);
        }

        return $update;
    }

    /**
     * Creates a VALUES statement object
     *
     * @param string|array|nodes\lists\RowList $rows
     * @return Values
     */
    public function values($rows): Values
    {
        if ($rows instanceof nodes\lists\RowList) {
            $rowList = $rows;
        } elseif (is_string($rows)) {
            $rowList = nodes\lists\RowList::createFromString($this->getParser(), $rows);
        } else {
            // we don't pass $rows as it may contain strings/arrays instead of RowExpression's,
            // Parser may be needed for that
            $rowList = new nodes\lists\RowList();
        }

        $values = new Values($rowList);
        $values->setParser($this->getParser());

        if (!is_string($rows) && !($rows instanceof nodes\lists\RowList)) {
            $values->rows->replace($rows);
        }

        return $values;
    }
}
