.. _statement-factory:

=================================
``StatementFactory`` helper class
=================================

``sad_spirit\pg_builder\StatementFactory`` is, as its name implies, a class that deals with creating statements.
It also takes care of properly configuring objects needed to create these statements based on PostgreSQL connection,
if one is given.

Public API
==========

.. code-block:: php

    namespace sad_spirit\bg_builder;

    use sad_spirit\pg_wrapper\Connection;

    class StatementFactory
    {
        // Constructor methods
        public function __construct(
            ?Parser $parser = null,
            ?StatementToStringWalker $builder = null,
            bool $PDOCompatible = false
        );
        public static function forConnection(Connection $connection) : self;
        public static function forPDO(\PDO $pdo) : self;

        // Getters
        public function getParser() : Parser;
        public function getBuilder() : StatementToStringWalker;

        // Converting SQL to AST and back
        public function createFromString(string $sql) : Statement;
        public function createFromAST(Statement $ast, bool $forcePDOPrepareCompatibility = false) : NativeStatement;

        // Factory methods for Statement subclasses
        public function delete(nodes\range\UpdateOrDeleteTarget|string $from) : Delete;
        public function insert(nodes\QualifiedName|nodes\range\InsertTarget|string $into) : Insert;
        public function merge(
            nodes\range\UpdateOrDeleteTarget|string $into,
            nodes\range\FromElement|string $using,
            nodes\ScalarExpression|string $on
        ) : Merge;
        public function select(
            string|iterable<nodes\TargetElement|string> $list,
            string|iterable<nodes\range\FromElement>|null $from = null
        ) : Select;
        public function update(
            nodes\range\UpdateOrDeleteTarget|string $table,
            string|iterable<nodes\SingleSetClause|nodes\MultipleSetClause|string> $set
        ) : Update;
        public function values(
            string|iterable<
                nodes\expressions\RowExpression|
                string|
                iterable<nodes\ScalarExpression|nodes\SetToDefault|string>
            > $rows
        ) : Values;
    }


Constructor methods and getters
===============================

Constructor arguments
    ``__construct()`` accepts an instance of ``Parser`` and an implementation of ``StatementToStringWalker``,
    the latter is implemented by ``SqlBuilderWalker``. If these are not provided, default instances will be created.

    ``$PDOCompatible`` flag triggers generating queries targeting PDO rather than native pgsql extension. Specifically,

    - Named parameters will not be replaced by positional ones;
    - Dollar-quoting will not be used;
    - If the query has parameter placeholders, question marks used in operators will be doubled so that
      ``\PDO::prepare()`` will not treat them as placeholders as well.

``forConnection()`` and ``forPDO()`` "named constructors"
    These methods create an instance of ``StatementFactory`` based on properties of a native connection or a PDO
    one, respectively. The latter also enables compatibility to PDO.

    The following settings will be configured:

    - ``Lexer`` instance used by ``Parser`` will follow server's `standard_conforming_strings
      setting <https://www.postgresql.org/docs/current/static/runtime-config-compatible.html#GUC-STANDARD-CONFORMING-STRINGS>`__.
    - If ``client_encoding`` is anything but ``UTF-8`` then ``SqlBuilderWalker`` will have ``escape_unicode`` enabled.
    - Additionally, ``Parser`` will reuse the metadata cache of ``Connection`` for caching ASTs, if available.

``getParser()`` and ``getBuilder()``
    These are self-explanatory, returning properties set in the constructor.

Conversion methods
==================

These use ``Parser`` and ``SqlBuilderWalker`` to convert query from SQL string to AST and back:

``createFromString()``
    Creates an AST representing a complete statement from SQL string. Returns an instance of ``Statement`` subclass,
    already having an instance of ``Parser`` added to it (so it can accept strings as query parts).

``createFromAST()``
    Creates an object containing SQL statement string and parameter mappings from AST. The
    returned ``NativeStatement`` object can be easily cached to prevent re-running expensive parsing and
    building operations.

    If ``$forcePDOPrepareCompatibility`` flag is ``true``, then generated SQL will be compatible
    with ``\PDO::prepare()`` even if ``$PDOCompatible`` flag was not passed to constructor or if the query
    does not contain parameter placeholders,
    see `the relevant issue <https://github.com/sad-spirit/pg-builder/issues/15>`__.

Creating ``Statement``\ s
=========================

The following methods are wrappers around ``Statement`` subclasses' constructors. Their added value is

- They accept strings in addition to ``Node`` implementations,
- The ``Statement``\ s they create will have ``Parser`` already added.

``delete()``
    Creates a ``DELETE`` statement object.

``insert()``
    Creates an ``INSERT`` statement object.

``merge()``
    Creates a ``MERGE`` statement object.

``select()``
    Creates a ``SELECT`` statement object. The ``$list`` and ``$from`` can be strings or arrays of strings
    or proper ``Node`` implementations.

``update()``
    Creates an ``UPDATE`` statement object. The ``$set`` argument can be an array of strings or proper ``Node``
    implementations.

``values()``
    Create a ``VALUES`` statement object (this can be a separate statement in Postgres).
    If ``$rows`` argument is an array or iterable, its first dimension represents rows and the second one represents
    columns.
