.. _walkers:

==============================
Modifying AST and building SQL
==============================

The query is represented as a tree of ``Node``\ s so it isn't difficult to write naïve functions working with said tree:

.. code-block:: php

   use sad_spirit\pg_builder\StatementFactory;
   use sad_spirit\pg_builder\Node;
   use sad_spirit\pg_builder\nodes\{
       lists\FromList,
       range\JoinExpression,
       range\RelationReference
   }

   function listTables(Node $node)
   {
       if ($node instanceof FromList) {
           foreach ($node as $child) {
               listTables($child);
           }

       } elseif ($node instanceof JoinExpression) {
           listTables($node->left);
           listTables($node->right);

       } elseif ($node instanceof RelationReference) {
           echo $node->name;
           if ($node->tableAlias) {
               echo " aliased as " . $node->tableAlias;
           }
           echo "\n";
       }
   }

   $factory = new StatementFactory();
   $select  = $factory->createFromString(
       'select * from foo left join (bar.baz as bb natural join quux) using (foo_id), another.source as s2, foo as f2'
   );

   echo "List of tables used in query:\n";
   listTables($select->from); 

will output

.. code-block:: output

   List of tables used in query:
   "foo"
   "bar"."baz" aliased as "bb"
   "quux"
   "another"."source" aliased as "s2"
   "foo" aliased as "f2"

This, of course, misses common table expressions, subselects in various parts of query, etc.
A better way is to create a ``TreeWalker`` implementation for processing AST.

.. _walkers-interface:

``TreeWalker`` interface
========================

``TreeWalker`` interface defines methods (**128** at the time of this writing) for visiting various
implementations of ``Node`` that appear in the Abstract Syntax Tree. It is not necessary to learn these methods' names,
as ``Node`` defines a ``dispatch()`` method: its implementations are expected to call the method of ``TreeWalker``
used for processing the current class.

**pg_builder** contains one child interface of ``TreeWalker``

``StatementToStringWalker``
    Adds explicit ``string`` return type hints  to methods accepting ``Statement`` instances, so that
    ``StatementFactory::createFromAST()`` will work as expected.

    It also defines an ``enablePDOPrepareCompatibility(bool $enable): void`` method that should prevent problems
    when using the generated statement with ``\PDO::prepare()`` once enabled.

and the following ``TreeWalker`` implementations:

``BlankWalker``
    A convenience class for creating ``TreeWalker`` implementations. Its methods only do dispatch to child nodes of
    the current node.

        ``ParameterWalker``
            A tree walker that extracts information about parameters' types and replaces named parameters with
            positional ones.

``SqlBuilderWalker`` (implementing ``StatementToStringWalker``)
    A tree walker that generates SQL from abstract syntax tree.

.. note::

    It is not recommended to directly implement ``TreeWalker``, as new methods will be added to it once
    new syntax of Postgres is supported. Create a subclass of ``BlankWalker`` instead.

Extending ``BlankWalker``
=========================

The above implementation can be redone using ``BlankWalker`` with the added benefit that it will handle
instances of ``RelationReference`` appearing anywhere in the query:

.. code-block:: php

   use sad_spirit\pg_builder\{
       StatementFactory,
       BlankWalker
   };
   use sad_spirit\pg_builder\nodes\range\RelationReference;

   class TableWalker extends BlankWalker
   {
       public function walkRelationReference(RelationReference $rangeItem)
       {
           echo $rangeItem->name;
           if ($rangeItem->tableAlias) {
               echo " aliased as " . $rangeItem->tableAlias;
           }
           echo "\n";
       }
   }

   $factory = new StatementFactory();
   $select  = $factory->createFromString(
       'select * from foo left join (bar.baz as bb natural join quux) using (foo_id), another.source as s2, foo as f2'
   );

   echo "List of tables used in query:\n";
   $select->dispatch(new TableWalker());

We only had to override the method of ``TreeWalker`` dealing with ``RelationReference`` nodes
(its name could be found out by checking ``RelationReference::dispatch()``).

.. _walkers-parameters:

``ParameterWalker`` class
=========================

This class is used internally by :ref:`StatementFactory::createFromAST() <statement-factory-conversion>` to

- Replace named parameters by positional ones;
- Infer parameter types from SQL typecasts. Type info can later be used
  by :ref:`converters\\BuilderSupportDecorator <queries>` for converting parameters.

Using named parameters like ``:foo`` instead of standard PostgreSQL's positional ``$1`` has obvious benefits
when query is being built: it is far easier to create unique parameter names than to assign successive
numbers when query parts are added all over the place.

Also, having the means to extract type info directly from query allows us to specify it only once and make it available
both to Postgres and to PHP code.

.. code-block:: php

   use sad_spirit\pg_builder\{
       StatementFactory,
       converters\ParserAwareTypeConverterFactory
   };
   use sad_spirit\pg_wrapper\Connection;

   $connection = new Connection('host=localhost user=postgres dbname=postgres');
   $factory    = StatementFactory::forConnection($connection);
   $connection->setTypeConverterFactory(new ParserAwareTypeConverterFactory($factory->getParser()));

   $native = $factory->createFromAST($factory->createFromString(
       'select typname from pg_catalog.pg_type where oid = any(:oid::integer[]) order by typname'
   ));
   $result = $native->executeParams($connection, ['oid' => [21, 23]]);
   var_dump($result->fetchColumn('typname'));

outputs

.. code-block:: output

   array(2) {
     [0] =>
     string(4) "int2"
     [1] =>
     string(4) "int4"
   }

``SqlBuilderWalker`` class
==========================

This class, as its name implies, is used for generating SQL. It is used internally
by ``StatementFactory::createFromAST()``.

SQL generated by this class is intended to be somewhat human-readable, so it tries to indent parts of query
and keep line lengths below reasonable limit. This is configured via constructor, it accepts an array with the
following options:

``'indent'``
    String used to indent parts of query, defaults to four spaces.

``'linebreak'``
    String used to separate lines, defaults to ``"\n"``.

``'wrap'``
    Try to keep lines shorter than this, defaults to 120.

``'escape_unicode'``
    If set to true, non-ASCII characters in string constants and identifiers will be represented by Unicode escape
    sequences.

Calling ``$builder->enablePDOPrepareCompatibility(true)`` on an instance of ``SqlBuilderWalker``
triggers escaping of operators containing a question mark and prevents generating dollar-quoted strings.
This method is called automatically by ``StatementFactory`` if PDO compatibility was requested.
