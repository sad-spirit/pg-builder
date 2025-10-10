.. _statements:

===================================
Classes representing SQL statements
===================================

Subclasses of ``\sad_spirit\pg_builder\Statement`` represent the complete SQL statements.
Their instances are usually created by :ref:`StatementFactory methods <statement-factory>`.

All relative class and interface names below assume ``\sad_spirit\pg_builder`` prefix, which is omitted for readability.

Class hierarchy
===============

``Node``
    :ref:`Common interface <base-node>` for all nodes in Abstract Syntax Tree.

        ``nodes\GenericNode``
            :ref:`Common base class<base-generic-node>` for all implementations of ``Node`` within package.

                ``Statement``
                    Abstract base class for statements. Loosely corresponds to ``PreparableStmt`` production
                    in PostgreSQL's grammar.

                        ``Delete``
                            Represents ``DELETE`` statement.

                        ``Insert``
                            Represents ``INSERT`` statement.

                        ``Merge``
                            Represents ``Merge`` statement.

                        ``Update``
                            Represents ``UPDATE`` statement.

                        ``SelectCommon``
                            Abstract base class for ``SELECT``-type statements.

                                ``Select``
                                    Represents a simple ``SELECT`` statement.

                                ``SetOpSelect``
                                    Represents a set operator (``UNION``, ``INTERSECT``, ``EXCEPT``)
                                    applied to two ``SELECT`` statements.

                                ``Values``
                                    Represents ``VALUES`` statement. Note that this can be an independent statement
                                    in PostgreSQL, not only a part of ``INSERT``.

Clauses of SQL statements, e.g. ``WHERE`` or ``FROM``, are exposed as properties of the relevant objects.
Those properties can be writable if a corresponding ``setProperty()`` method is defined for ``$property`` and
read-only otherwise.

.. warning::

    The setter methods for properties should be considered an implementation detail, which may change in the future.
    Assign new values directly to the properties, e.g.

    .. code-block:: php

        $select->distinct = true;

    rather than via setters

    .. code-block:: php

        $select->setDistinct(true);

If the clause is a list of items, like ``FROM``, then the corresponding property is usually read-only and
is an :ref:`implementation of NodeList <base-nodelist>` and consequently ``\ArrayAccess``.
The items in the list may be modified as array offsets:

.. code-block:: php

    $select->from[0] = 'foo as bar';
    unset($select->from[1]);

.. _statements-base:

``Statement`` methods and properties
====================================

``$with: nodes\WithClause``
    A writable property that represents a ``WITH`` clause containing
    `Common Table Expressions <https://www.postgresql.org/docs/current/queries-with.html>`__
    attached to a primary statement.

``setParser(Parser $parser)``
    Sets the parser instance to use. If you add a ``Parser`` to the ``Statement`` then you'll be able to add
    parts of query as strings that will be parsed automatically.

``getParser(): ?Parser``
    Returns the parser instance, if available.

It is always possible to add query parts by creating the relevant ``Node`` implementations, but this is very tedious:

.. code-block:: php

   $select->list[] = new nodes\TargetElement(new nodes\ColumnReference('foo', 'bar'), new nodes\Identifier('alias'));

vs

.. code-block:: php

   $select->list[] = 'foo.bar as alias';

the result of the above is the same, as the string will be parsed and an instance of ``TargetElement`` added.

.. tip::

    ``Statement`` instances created by ``StatementFactory`` will have a ``Parser`` set.

``nodes\WithClause``
~~~~~~~~~~~~~~~~~~~~

This is an implementation of ``NodeList`` so individual CTEs (instances of ``nodes\CommonTableExpression``)
are accessible as array offsets. It also has a writable boolean ``$recursive`` property.

.. code-block:: php

   $select->with[] = 'foobar as (select foo.*, bar.* from foo natural join bar)';

   echo "WITH clause is " . ($select->with->recursive ? 'recursive' : 'not recursive');
   echo "Statement of first CTE is " . get_class($select->with[0]->statement);  

``Delete`` properties
=====================

``$relation: nodes\range\UpdateOrDeleteTarget``
    Name of the table to delete from. Can be set only via constructor.

``$using: nodes\lists\FromList``
    List of tables whose columns may appear in ``WHERE`` clause. ``FromList`` implements ``NodeList``
    and behaves like an array containing only instances of ``nodes\range\FromElement``.

``$where: nodes\WhereOrHavingClause``
    ``WHERE`` clause of ``DELETE``. ``$where`` property is read-only, but has :ref:`helper methods <helpers>` for
    building the ``WHERE`` clause.

``$returning: nodes\ReturningClause``
    ``RETURNING`` clause of ``DELETE``. If present, ``DELETE`` will return values based on each
    deleted row. ``ReturningClause`` is essentially an array containing only instances of ``nodes\TargetElement``
    with additional properties representing aliases for ``OLD`` and ``NEW`` in Postgres 18+.

``Insert`` properties
=====================

``$relation: nodes\range\InsertTarget``
    Name of the table to insert into. Can be set only via constructor.

``$cols: nodes\lists\SetTargetList``
    List of table's columns to use. ``SetTargetList`` is essentially an array containing only instances of
    ``nodes\SetTargetElement``.

``$values: SelectCommon``
    Actual values to insert. This property is writable.

``$overriding: enums\InsertOverriding|null``
    ``OVERRIDING`` clause. The property is writable.

``$onConflict: nodes\OnConflictClause``
    ``ON CONFLICT`` clause used  to specify an alternative action to raising a unique constraint or
    exclusion constraint violation error. The property is writable.

``$returning: nodes\ReturningClause``
    ``RETURNING`` clause of ``INSERT``, if present ``INSERT`` will return values based on each
    inserted (or maybe updated in case of ``ON CONFLICT ... DO UPDATE``)
    row. ``ReturningClause`` is essentially an array containing only instances of ``nodes\TargetElement``
    with additional properties representing aliases for ``OLD`` and ``NEW`` in Postgres 18+.

``Merge`` properties
=====================

``$relation: nodes\range\UpdateOrDeleteTarget``
    Name of the ``MERGE`` target table. This property is writable.

``$using: nodes\range\FromElement``
    Data source for ``MERGE``. This property is writable.

``$on: nodes\ScalarExpression``
    Condition for joining data source to target table. This property is writable.

``$when: nodes\merge\MergeWhenList``
    List of ``WHEN`` conditions for ``MERGE``. ``MergeWhenList`` behaves like an array containing only instances
    of ``nodes\merge\MergeWhenClause``.

``$returning: nodes\ReturningClause``
    ``RETURNING`` clause of ``MERGE``.
    ``ReturningClause`` behaves like an array containing only instances of ``nodes\TargetElement``.

``Update`` properties
=====================

``$relation: nodes\range\UpdateOrDeleteTarget``
    Name of the table to update. Can be set only via constructor.

``$set: nodes\lists\SetClauseList``
    ``SET`` clause of ``UPDATE`` statement. ``SetClauseList`` is essentially an array containing only
    instances of either ``nodes\SingleSetClause`` or ``nodes\MultipleSetClause``.

``$from: nodes\lists\FromList``
    List of tables whose columns may appear in ``WHERE`` condition and the update expressions. ``FromList``
    is essentially an array containing only instances of ``nodes\range\FromElement``.

``$where: WhereOrHavingClause``
    ``WHERE`` clause of ``UPDATE``.

``$returning: nodes\ReturningClause``
    ``RETURNING`` clause of ``UPDATE``, if present ``UPDATE`` will return values based on each updated row.
    ``ReturningClause`` is essentially an array containing only instances of ``nodes\TargetElement``.

``SelectCommon`` methods and properties
=======================================

``$order: nodes\lists\OrderByList``
    ``ORDER BY`` clause of ``SELECT`` statement. ``OrderByList`` is essentially an array containing
    only instances of ``nodes\OrderByElement``.

``$limit: nodes\ScalarExpression``
    ``LIMIT`` clause of ``SELECT`` statement. This property is writable.

``$limitWithTies: bool``
    If ``true``, triggers generating SQL standard  ``FETCH FIRST ... ROWS WITH TIES`` clause.
    This property is writable.

``$offset: nodes\ScalarExpression``
    ``OFFSET`` clause of ``SELECT`` statement. This property is writable.

``$locking: nodes\lists\LockList``
    Locking clause of ``SELECT`` statement, consisting of e.g. ``FOR UPDATE ...`` clauses. ``LockList``
    is essentially an array containing only instances of ``nodes\LockingElement``.

.. _statements-select-set:

Methods for set operators
~~~~~~~~~~~~~~~~~~~~~~~~~

``SelectCommon`` also defines methods for applying set operators:

``public function union(string|self $select, bool $distinct = true): SetOpSelect``
    Combines this ``SELECT`` statement with another one using ``UNION [ALL]`` operator.

``public function intersect(string|self $select, bool $distinct = true): SetOpSelect``
    Combines this ``SELECT`` statement with another one using ``INTERSECT [ALL]`` operator.

``public function except(string|self $select, bool $distinct = true): SetOpSelect``
    Combines this ``SELECT`` statement with another one using ``EXCEPT [ALL]`` operator

If these methods are called on a ``SELECT`` statement that is a part of
some larger statement then result will replace the original statement:

.. code-block:: php

    use sad_spirit\pg_builder\{
        StatementFactory,
        Select
    };

    $factory = new StatementFactory();

    /** @var Select $select */
    $select = $factory->createFromString(
       'select foo.*, bar.* from (select * from foosource) as foo, bar where foo.id = bar.id'
    );
    $select->from[0]->query->union('select * from othersource');

    echo $factory->createFromAST($select)->getSql();

will output

.. code-block:: sql

    select foo.*, bar.*
    from (
            select *
            from foosource
            union
            select *
            from othersource
        ) as foo, bar
    where foo.id = bar.id

``Select`` properties
=====================

``$list: nodes\lists\TargetList``
    List of columns returned by ``SELECT``. ``TargetList`` behaves like an array containing only
    instances of ``nodes\TargetElement``, its subclass is also used for ``RETURNING`` clauses
    of data-modifying statements.

``$distinct: bool|nodes\lists\ExpressionList``
    ``true`` here represents ``DISTINCT`` clause, list of expressions - ``DISTINCT ON (...)`` clause.
    This property is writable.
    ``ExpressionList`` behaves like an array containing only implementations of ``nodes\ScalarExpression``.

``$from: nodes\lists\FromList``
    List of tables to select from. ``FromList`` behaves like an array containing only instances of
    ``nodes\range\FromElement``.

``$where: nodes\WhereOrHavingClause``
    ``WHERE`` clause of ``SELECT``.

``$group: nodes\group\GroupByClause``
    ``GROUP BY`` clause of ``SELECT``. ``GroupByClause`` has array offsets containing implementations of
    either ``nodes\ScalarExpression`` or ``nodes\group\GroupByElement`` interfaces, additionally it has a
    writable bool ``$distinct`` property.

``$having: nodes\WhereOrHavingClause``
    ``HAVING`` clause of ``SELECT``, the same class is used here as for ``$where`` property.

``$window: nodes\lists\WindowList``
    ``WINDOW`` clause of ``SELECT``. ``WindowList`` behaves like an array containing only instances of
    ``nodes\WindowDefinition``.

``SetOpSelect`` properties
==========================

``$left: SelectCommon``
    First operand of set operation. This property is writable.

``$right: SelectCommon``
    Second operand of set operation. This property is writable.

``$operator: enums\SetOperator``
    Operator, can be set only via constructor.

``Values`` properties
=====================

``$rows: nodes\lists\RowList``
    List of rows in ``VALUES``. ``RowList`` behaves like an array containing only instances of
    ``nodes\expressions\RowExpression``.
