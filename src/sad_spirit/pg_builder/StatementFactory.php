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

namespace sad_spirit\pg_builder;

use sad_spirit\pg_wrapper\Connection,
    sad_spirit\pg_wrapper\exceptions\InvalidQueryException;

/**
 * Helper class for creating statements and passing Parser object to them
 */
class StatementFactory
{
    /**
     * Database connection
     * @var Connection
     */
    private $_connection;

    /**
     * Query parser, will be passed to created statements
     * @var Parser
     */
    private $_parser;

    /**
     * Query builder
     * @var TreeWalker
     */
    private $_builder;

    /**
     * Constructor, can set the Connection and Parser objects
     *
     * @param Connection $connection
     * @param Parser $parser
     */
    public function __construct(Connection $connection = null, Parser $parser = null)
    {
        $this->_connection = $connection;
        $this->_parser     = $parser;
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
        if (!$this->_parser) {
            if (!$this->_connection) {
                $cache         = null;
                $lexerOptions  = array();
                $serverVersion = '9.5.0';

            } else {
                $cache = $this->_connection->getMetadataCache();
                try {
                    $res = $this->_connection->execute('show standard_conforming_strings');
                    $lexerOptions = array(
                        'standard_conforming_strings' => 'on' === $res[0]['standard_conforming_strings']
                    );
                } catch (InvalidQueryException $e) {
                    // the server is not aware of the setting?
                    $lexerOptions = array('standard_conforming_strings' => false);
                }

                $serverVersion = pg_parameter_status($this->_connection->getResource(), 'server_version');

            }
            $this->_parser = new Parser(new Lexer($lexerOptions), $cache);
            $this->_parser->setOperatorPrecedence(
                version_compare($serverVersion, '9.5.0', '>=')
                ? Parser::OPERATOR_PRECEDENCE_CURRENT : Parser::OPERATOR_PRECEDENCE_PRE_9_5
            );
        }
        return $this->_parser;
    }

    /**
     * Sets the SQL builder object used by createFromAST()
     *
     * @param TreeWalker $builder
     */
    public function setBuilder(TreeWalker $builder)
    {
        $this->_builder = $builder;
    }

    /**
     * Returns the SQL builder object
     *
     * If not explicitly set by setBuilder(), an instance of SqlBuilderWalker with default
     * options will be created.
     *
     * @return TreeWalker
     */
    public function getBuilder()
    {
        if (!$this->_builder) {
            if (!$this->_connection) {
                $serverVersion = '9.5.0';

            } else {
                $serverVersion = pg_parameter_status($this->_connection->getResource(), 'server_version');
            }

            $this->_builder = new SqlBuilderWalker(array(
                'parentheses' => version_compare($serverVersion, '9.5.0', '>=')
                                 ? SqlBuilderWalker::PARENTHESES_CURRENT : SqlBuilderWalker::PARENTHESES_COMPAT
            ));
        }
        return $this->_builder;
    }

    /**
     * Creates an AST representing a complete statement from SQL string
     *
     * @param string $sql
     * @return Statement
     * @throws exceptions\SyntaxException
     */
    public function createFromString($sql)
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
    public function createFromAST(Statement $ast)
    {
        $pw = new ParameterWalker();
        $ast->dispatch($pw);

        return new NativeStatement(
            $ast->dispatch($this->getBuilder()), $pw->getParameterTypes(), $pw->getNamedParameterMap()
        );
    }

    /**
     * Creates a DELETE statement object
     *
     * @param string|nodes\range\RelationReference $from
     * @return Delete
     */
    public function delete($from)
    {
        if ($from instanceof nodes\range\RelationReference) {
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
     * @param string|nodes\QualifiedName $into
     * @return Insert
     */
    public function insert($into)
    {
        if ($into instanceof nodes\QualifiedName) {
            $relation = $into;
        } else {
            $relation = $this->getParser()->parseQualifiedName($into);
        }

        $insert = new Insert($relation);
        $insert->setParser($this->getParser());

        return $insert;
    }

    /**
     * Creates a SELECT statement object
     *
     * @param string|array|nodes\lists\TargetList $list
     * @param string|array|nodes\lists\FromList   $from
     * @return Select
     */
    public function select($list, $from = null)
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
     * @param string|nodes\range\RelationReference   $table
     * @param string|array|nodes\lists\SetTargetList $set
     * @return Update
     */
    public function update($table, $set)
    {
        if ($table instanceof nodes\range\RelationReference) {
            $relation = $table;
        } else {
            $relation = $this->getParser()->parseRelationExpressionOptAlias($table);
        }

        if ($set instanceof nodes\lists\SetTargetList) {
            $setList = $set;
        } elseif (is_string($set)) {
            $setList = $this->getParser()->parseSetClause($set);
        } else {
            // we don't pass $set since it may contain strings instead of SetTargetElements,
            // Parser may be needed for that
            $setList = new nodes\lists\SetTargetList();
        }

        $update = new Update($relation, $setList);
        $update->setParser($this->getParser());

        if (!is_string($set) && !($set instanceof nodes\lists\SetTargetList)) {
            $update->set->replace($set);
        }

        return $update;
    }

    /**
     * Creates a VALUES statement object
     *
     * @param string|array|nodes\lists\CtextRowList $rows
     * @return Values
     */
    public function values($rows)
    {
        if ($rows instanceof nodes\lists\CtextRowList) {
            $rowList = $rows;
        } elseif (is_string($rows)) {
            $rowList = nodes\lists\CtextRowList::createFromString($this->getParser(), $rows);
        } else {
            // we don't pass $set since it may contain strings/arrays instead of CtextRows,
            // Parser may be needed for that
            $rowList = new nodes\lists\CtextRowList();
        }

        $values = new Values($rowList);
        $values->setParser($this->getParser());

        if (!is_string($rows) && !($rows instanceof nodes\lists\CtextRowList)) {
            $values->rows->replace($rows);
        }

        return $values;
    }
}