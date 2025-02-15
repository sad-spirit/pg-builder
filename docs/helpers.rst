==============================
Helper methods of node classes
==============================

Most of the ``Node`` impplementations don't add new API except for declaring properties and internal methods to support
those. The few  that have extra methods are described here.

``SelectCommon``
================

Common subclass for ``SELECT`` and ``VALUES`` statements. It has ``union()``, ``intersect()``, and ``except()``
methods for combining the current statement with another one using set operators.

These are described in the :ref:`section dealing with SelectCommon <statements-select-set>`.

``nodes\range\FromElement``
===========================

This is an abstract base class for elements appearing in ``FROM`` and similar SQL clauses.
It defines methods for joining another ``FROM`` element to the current one.

.. code-block:: php

    namespace sad_spirit\pg_builder\nodes\range;

    use sad_spirit\pg_builder\enums\JoinType;
    use sad_spirit\pg_builder\nodes\GenericNode;

    abstract class FromElement extends GenericNode
    {
        public function join(self|string $fromElement, JoinType $joinType = JoinType::INNER) : JoinExpression;

        public function innerJoin(self|string $fromElement) : JoinExpression;
        public function crossJoin(self|string $fromElement) : JoinExpression;
        public function leftJoin(self|string $fromElement) : JoinExpression;
        public function rightJoin(self|string $fromElement) : JoinExpression;
        public function fullJoin(self|string $fromElement) : JoinExpression;
    }

``join()``
    Creates a ``JOIN`` between this element and another one using given join type.
    The type is represented by a case of string-backed ``JoinType`` enum.

``innerJoin()`` / ``crossJoin()`` / ``leftJoin()`` / ``rightJoin()`` / ``fullJoin()``
    These are shorthand methods for ``join()``, passing, respectively
    ``JoinType::INNER``, ``JoinType::CROSS``, ``JoinType::LEFT``, ``JoinType::RIGHT``, and
    ``JoinType::FULL`` for its second argument.


If these methods are called on an element that is already a part of bigger AST then
result will replace the original element:

.. code-block:: php

   use sad_spirit\pg_builder\{
       StatementFactory,
       Select
   };

   $factory = new StatementFactory();

   /** @var Select $select */
   $select = $factory->createFromString(
       'select foo.*, bar.* from foo, bar where foo.id = bar.id'
   );
   $select->from[0]->leftJoin('baz')->on = 'foo.id = baz.foo_id';
   $select->list[] = 'baz.*';
   echo $factory->createFromAST($select)->getSql();

will output

.. code-block:: sql

   select foo.*, bar.*, baz.*
   from foo left join baz on foo.id = baz.foo_id, bar
   where foo.id = bar.id

``nodes\WhereOrHavingClause``
=============================

This is a wrapper around an object implementing ``nodes\ScalarExpression`` that represents the ``WHERE`` condition of
``SELECT`` / ``UPDATE`` / ``DELETE`` or ``HAVING`` condition of ``SELECT``.
It contains methods for combining parts of the condition with logical ``AND`` and ``OR`` operators.

.. code-block:: php

    namespace sad_spirit\pg_builder\nodes;

    /**
     * @property ScalarExpression|null $condition
     */
    class WhereOrHavingClause extends GenericNode
    {
        public function and(self|string|ScalarExpression|null $condition) : $this;
        public function or(self|string|ScalarExpression|null $condition) : $this;
        public function nested(self|string|ScalarExpression $condition) : self;
    }


``$condition``
    The actual wrapped condition, this property is writable and can accept strings as input.
    Setting it will completely replace the condition.

``and()``
    Adds a condition to the clause using ``AND`` operator.

``or()``
    Adds a condition to the clause using ``OR`` operator.

``nested()``
    Helper method for creating nested conditions. Basically this allows adding parentheses to logical expressions.

.. code-block:: php

   use sad_spirit\pg_builder\{
       StatementFactory,
       Select
   };

   $factory = new StatementFactory();

   /** @var Select $select */
   $select = $factory->createFromString(
       'select * from foo where blah'
   );

   $select->where->condition = 'foo_one = 1';
   $select->where->and('foo_two = 2');
   $select->where->or("foo_title ~ 'foo'");
   $select->where->and(
       $select->where->nested("foo_pubdate > 'yesterday'")
           ->or("foo_important")
   );

   echo $factory->createFromAST($select)->getSql();

outputs

.. code-block:: sql

   select *
   from foo
   where foo_one = 1
       and foo_two = 2
       or foo_title ~ 'foo'
       and (
           foo_pubdate > 'yesterday'
           or foo_important
       )
