================
Typical workflow
================

Start building a query
======================

There are three ways to start building a query with **pg_builder**. The first one
:ref:`involves StatementFactory <statement-factory>`
and will be immediately familiar to users of more traditional query builders:

.. code-block:: php

    use sad_spirit\pg_builder\StatementFactory;

    $select = (new StatementFactory())
        ->select('foo_id as id, foo_title, foo_description', 'foo');

What's may be less familiar is that we are passing the list of columns as string instead of the usual array.
The resultant :ref:`$select statement <statements>`, however, won't contain that string, it will contain several
``Node``\ s representing target fields (and relations in ``FROM``).

Those ``Node``\ s can also be created manually and that represents the second way to build a query:

.. code-block:: php

   use sad_spirit\pg_builder\Select;
   use sad_spirit\pg_builder\nodes\{
       ColumnReference,
       Identifier,
       QualifiedName,
       TargetElement,
       lists\TargetList,
       range\RelationReference
   };

   $select = new Select(new TargetList([
       new TargetElement(new ColumnReference('foo_id'), new Identifier('id')),
       new TargetElement(new ColumnReference('foo_title')),
       new TargetElement(new ColumnReference('foo_description'))
   ]));
   $select->from[] = new RelationReference(new QualifiedName('foo'));

However it is extremely verbose and you are unlikely to do this very often, if ever.

.. tip::

    The code that allows us to add query parts as strings but have a tree representing the query as a result
    is a :ref:`reimplementation of PostgreSQL's parser <parsing>`.

The third way that is unique to **pg_builder** is starting from a manually written query

.. code-block:: php

    use sad_spirit\pg_builder\StatementFactory;

    $select = (new StatementFactory())->createFromString("
        select foo_id as id, foo_title, foo_description, bar_title, bar_description
        from foo, bar
        where foo.foo_id = bar.foo_id
   ");

and updating it afterwards. This, of course, also depends on the ``Parser``.

Add elements to the query
=========================

Various clauses of ``SELECT`` statement are exposed as properties of ``$select`` object.
Those are either directly writable or :ref:`behave like arrays <base-nodelist>`
or :ref:`have some helper methods <helpers>` for manipulation:

.. code-block:: php

   $select->distinct = true;
   $select->list[] = 'baz_source';
   $select->from[0]->leftJoin('someschema.baz')->on = 'foo.baz_id = baz.baz_id';
   $select->where->and('foo_title ~* $1');

Note that while the above still looks like adding strings to the object,
reality is a bit more complex:

.. code-block:: php

   try {
       $select->list[] = 'where am I?';
   } catch (\Exception $e) {
       echo $e->getMessage();
   }

will output

.. code-block:: output

   Unexpected keyword 'where' at position 0 (line 1), expecting identifier: where am I?

A less obvious one

.. code-block:: php

   try {
       $select->list->merge('foo(bar := baz, quux)');
   } catch (\Exception $e) {
       echo $e->getMessage();
   }

will output

.. code-block:: output

   Positional argument cannot follow named argument at position 16 (line 1): quux)

It is possible to build a syntactically incorrect statement with **pg_builder** but most errors are caught.

Of course, you can directly add parts of the query as ``Node`` implementations rather than strings

.. code-block:: php

    use sad_spirit\pg_builder\enums\ConstantName;
    use sad_spirit\pg_builder\nodes\expressions\KeywordConstant;

    $select->where->and(new KeywordConstant(ConstantName::FALSE));

.. note::

    If you make a typo in the table's name, the package won't catch it, as it does not try to check database's metadata.
    In PostgreSQL itself this is done in query
    `transformation process <https://www.postgresql.org/docs/current/static/parser-stage.html>`__
    which starts after the parsing.

Analyze and transform the query
===============================

Unlike traditional query builders where you usually add query parts to
some "black box" and can't even check the contents of this box
afterwards, query parts in **pg_builder** are both writable *and*
readable. If you do

.. code-block:: php

   $select->list->replace('count(*)');

somewhere in you script to build a query for total number of rows (e.g. for paging) instead of the query
actually returning rows, you can later check

.. code-block:: php

   use sad_spirit\pg_builder\nodes\expressions\FunctionExpression;

   $isTotalRows = 1 === count($select->list)
                  && $select->list[0]->expression instanceof FunctionExpression
                  && 'count' === $select->list[0]->expression->name->relation->value);

   if (!$isTotalRows) {
       // add some fields to $select->list
       // add some left- or right-join tables
   }
   $select->where->and(/* some criterion that should be both in usual and in count(*) query */);

or using ``SqlBuilderWalker`` this can be done in a bit more readable way

.. code-block:: php

   use sad_spirit\pg_builder\SqlBuilderWalker;

   $isTotalRows = 1 === count($select->list)
                  && 'count(*)' === $select->list[0]->dispatch(new SqlBuilderWalker());

It is sometimes needed to analyze the whole AST rather than a single known part of it:
you can use an :ref:`implementation of TreeWalker <walkers>` for this.
For example, the ``ParameterWalker`` class of the package
processes the query and replaces named parameters ``:foo`` that are not natively supported by PostgreSQL
to native positional parameters and infers the parameters' types from SQL typecasts.

Generate SQL
============

This is as simple as (if using ``StatementFactory``)

.. code-block:: php

   $native = $factory->createFromAST($select);

Under the hood this uses another implementation of ``TreeWalker``: ``SqlBuilderWalker``. The returned value
is not a ``string`` but an instance of :ref:`NativeStatement object <queries-nativestatement>`.
It contains both the generated SQL and info on query parameters extracted using the ``ParameterWalker`` mentioned above.

Execute the generated SQL: pg_wrapper
=====================================

The package contains several classes that are used for integration with **pg_wrapper** package:
``StatementFactory``, ``NativeStatement``, ``converters\BuilderSupportDecorator``.

A few steps are required to configure that integration

.. code-block:: php

    use sad_spirit\pg_builder\{
        StatementFactory,
        converters\BuilderSupportDecorator
    };
    use sad_spirit\pg_wrapper\Connection;

    $connection = new Connection('...');
    // Uses DB connection properties to set up parsing and building of SQL
    $factory    = StatementFactory::forConnection($connection);
    // Needed for handling type info extracted from query
    $connection->setTypeConverterFactory(new BuilderSupportDecorator(
        $connection->getTypeConverterFactory(),
        $factory->getParser()
    ));

then you can build queries with **pg_builder**

.. code-block:: php

   $native = $factory->createFromAST($factory->createFromString(
       "select * from foo where foo_id = any(:id::integer[])"
   ));

and execute them with **pg_wrapper** using named parameters and not specifying types:

.. code-block:: php

   $native->executeParams($connection, ['id' => [1, 2, 3]]);

as ``$native`` has knowledge about mapping of named parameter ``:id`` to ``$1`` and about its type.
This is another difference from the usual query builders where you may need to specify the type of a parameter once for
the builder and possibly second time for the database.

Execute the generated SQL: PDO
==============================

It is possible to generate queries suitable for PDO, though type conversion will be done manually

.. code-block:: php

    $pdo       = new \PDO('pgsql:...');
    // Uses DB connection properties to set up parsing and building of SQL
    $factory   = StatementFactory::forPDO($pdo);
    // NB: This still requires sad_spirit/pg_wrapper for type conversion code
    $converter = new BuilderSupportDecorator(new DefaultTypeConverterFactory(), $factory->getParser());

Assuming the same code to generate ``$native``, it can be executed this way

.. code-block:: php

    $result = $pdo->prepare($native->getSql());
    $result->execute($converter->convertParameters(
        $native,
        ['id' => [1, 2, 3]]
    ));

.. tip::

    When generating queries for PDO, named parameters will not be replaced by positional ones.