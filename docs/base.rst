.. _base:

===========================
Base classes and interfaces
===========================

``\sad_spirit\pg_builder\Node`` is an interface implemented by all AST
nodes. Its descendant ``\sad_spirit\pg_builder\NodeList`` is a interface
for nodes representing lists in SQL expressions (e.g. list of columns
returned by a ``SELECT`` or list of tables in ``FROM``).

Note that all relative class and interface names below assume
``\sad_spirit\pg_builder\`` prefix, which is omitted for readability.

.. _base-node:

``Node`` interface
==================

.. code-block:: php

    namespace sad_spirit\pg_builder;

    interface Node
    {
        public function dispatch(TreeWalker $walker) : mixed;

        public function getParser() : ?Parser;

        public function setParentNode(?Node $parent) : void;
        public function getParentNode() : ?Node;

        public function replaceChild(Node $oldChild, Node $newChild) : ?Node;
        public function removeChild(Node $child) : ?Node;
    }

``dispatch()``
    This is a double-dispatch method supposed to call the method of ``TreeWalker`` relevant for the current
    ``Node``.

``getParser()``
    Returns the ``Parser``, if one is available. It will usually be
    :ref:`added to a Statement instance <statements-base>` that contains the current ``Node``.
    The ``GenericNode`` implementation of this just method calls ``getParser()`` of its parent ``Node``.

.. _base-parent:

Parent node references
----------------------

``setParentNode()``
    Adds the link to the ``Node`` containing current one.

``getParentNode()``
    Returns the ``Node`` containing current one.

Nodes in the AST keep track of their parent nodes and can only be a child of one parent.
This means that if you want to copy some node from one branch of the AST to the other, you actually need to clone it.
Otherwise the result might be unexpected,

.. code-block:: php

   use sad_spirit\pg_builder\StatementFactory;

   $factory = new StatementFactory();

   /** @var \sad_spirit\pg_builder\SetOpSelect $select  */
   $select = $factory->createFromString(
       'select foo_id, title, description, pub_date from foosourse union all select bar_id from barsource'
   );
   // let's copy the field list to the second argument of union
   foreach ($select->left->list as $k => $v) {
       if ($k > 0) {
           $select->right->list[] = $v;
       }
   }

   echo $factory->createFromAST($select)->getSql();

will print

.. code-block:: sql

   select foo_id
   from foosourse
   union all
   select bar_id, title, description, pub_date
   from barsource

If you change the assignment in the loop to ``$select->right->list[] = clone $v;`` the result will be

.. code-block:: sql

   select foo_id, title, description, pub_date
   from foosourse
   union all
   select bar_id, title, description, pub_date
   from barsource

Handling "any child"
--------------------

Usually you work with node's children through exposed properties, but
``Node`` defines two special methods that allow working with *any* child:

``replaceChild()``
    Replaces the child ``Node`` with another one.

``removeChild()``
    Removes the child ``Node`` (more precisely, tries to store a ``null`` in a relevant property).

These methods are useful for applications that transform AST: e.g. when ``ParameterWalker`` instance needs to replace
a node for a named parameter ``:foo`` with a node for a positional parameter ``$1`` it just calls ``replaceChild()``
on the ``Parameter``'s parent node. It doesn't care about that node's type and doesn't know to what property of
the parent the ``Parameter`` node is mapped.

``nodes\ScalarExpression`` interface
------------------------------------

.. code-block:: php

    namespace sad_spirit\pg_builder\nodes;

    use sad_spirit\pg_builder\enums\ScalarExpressionAssociativity;
    use sad_spirit\pg_builder\enums\ScalarExpressionPrecedence;
    use sad_spirit\pg_builder\Node;

    interface ScalarExpression extends Node
    {
        public function getPrecedence() : ScalarExpressionPrecedence;
        public function getAssociativity() : ScalarExpressionAssociativity;
    }

This is implemented by ``Node``\ s that are used in scalar expressions. It is widely used for type hints and
defines methods used to properly add parentheses when generating SQL:

``getPrecedence()``
    Returns the integer-backed value specifying relative precedence of this ``ScalarExpression``.

``getAssociativity()``
    Returns the associativity (left / right / non-associative) for this ``ScalarExpression``.

``nodes\FunctionLike`` interface
--------------------------------

.. code-block:: php

    namespace sad_spirit\pg_builder\nodes;

    use sad_spirit\pg_builder\Node;

    interface FunctionLike extends Node
    {
    }

This interface is implemented by all ``Node``\ s that are considered functions in Postgres grammar.
Those ``Node``\ s can be used instead of "normal" function calls in ``FROM`` clause, e.g.

.. code-block:: sql

    select * from localtimestamp;

is allowed in Postgres and thus ``nodes\expressions\SQLValueFunction`` node backing ``localtimestamp`` expression
implements ``FunctionLike``.

.. _base-generic-node:

``nodes\GenericNode`` class
---------------------------

This abstract class is a default implementation of ``Node``, it implements all its methods except ``dispatch()``.
All the node classes in **pg_builder** extend ``GenericNode``.

Additionally, ``GenericNode`` implements the following magic methods:

``__get()`` / ``__set()`` / ``__isset()``
    These allow access to child nodes as properties.

``__clone()``
    This performs deep cloning of child nodes, which is needed for correct handling of
    :ref:`parent node references <base-parent>`

``__serialize()`` / ``__unserialize()``
    These are needed to support caching of ASTs.

.. _base-nodelist:

``NodeList`` interface
======================

.. code-block:: php

    namespace sad_spirit\pg_builder;

    /**
     * @template TKey of array-key
     * @template T
     * @template TListInput
     */
    interface NodeList extends Node, \ArrayAccess<TKey, T>, \Countable, \IteratorAggregate<TKey, T>
    {
        public function merge(TListInput ...$lists) : void;
        public function replace(TListInput $list) : void;
    }

Instances of ``NodeList``  behave like typed arrays / collections, allowing only objects of specific class
or implementing specific interfaces as their elements.

Its additional methods are

``merge()``
    Merges one or more lists with the current one.

``replace()``
    Replaces the elements of the list with the given ones.

The ``TListInput`` template usually is a union type having a ``string`` as one of the options, those strings
will be processed by ``Parser`` if one is available.

``Parseable`` and ``ElementParseable`` interfaces
-------------------------------------------------

.. code-block:: php

    namespace sad_spirit\pg_builder;

    interface Parseable
    {
        public static function createFromString(Parser $parser, string $sql) : self;
    }

    /**
     * @template T of Node
     */
    interface ElementParseable
    {
        public function createElementFromString(string $sql) : T;
    }

Classes implementing ``Parseable`` allow string arguments for ``merge()`` and ``replace()`` calls.

Classes implementing ``ElementParseable`` allow string arguments for ``offsetSet()``
and consequently for array offset assignment.

.. code-block:: php

   $select->list->merge('foo.id as foo_id, bar.title as bar_title');

   $select->list[] = 'baz.*';

.. tip::
    If you want to add several elements to the list at once, one ``merge()`` call with a string argument
    will be cheaper in terms of overhead than several assignments with string arguments.

``nodes\lists\GenericNodeList`` class
-------------------------------------

This abstract class extending ``nodes\GenericNode`` is a default implementation of ``NodeList``.
It also updates methods of ``nodes\GenericNode`` to work with array offset as well as properties.

All the lists in the package, except ``nodes\lists\FunctionArgumentList``,
inherit from its subclass ``nodes\lists\NonAssociativeList`` which disallows non-numeric array keys.

Notable ``NodeList`` implementations
------------------------------------

The following implementations of ``NodeList`` appear as properties of :ref:`Statement objects <statements>`,
all of them implement ``Parseable`` and ``ElementParseable`` interfaces:

+--------------------------------+-----------------------------------------------------------------------------------+
| ``NodeList`` subclass          | Allowed elements                                                                  |
+================================+===================================================================================+
| ``nodes\lists\ExpressionList`` | objects implementing ``nodes\ScalarExpression``                                   |
+--------------------------------+-----------------------------------------------------------------------------------+
| ``nodes\lists\FromList``       | instances of ``nodes\range\FromElement``                                          |
+--------------------------------+-----------------------------------------------------------------------------------+
| ``nodes\group\GroupByClause``  | objects implementing ``nodes\ScalarExpression`` or ``nodes\group\GroupByElement`` |
+--------------------------------+-----------------------------------------------------------------------------------+
| ``nodes\lists\LockList``       | instances of ``nodes\LockingElement``                                             |
+--------------------------------+-----------------------------------------------------------------------------------+
| ``nodes\merge\MergeWhenList``  | instances of ``nodes\merge\MergeWhenClause``                                      |
+--------------------------------+-----------------------------------------------------------------------------------+
| ``nodes\lists\OrderByList``    | instances of ``nodes\OrderByElement``                                             |
+--------------------------------+-----------------------------------------------------------------------------------+
| ``nodes\lists\RowList``        | instances of ``nodes\expressions\RowExpression``                                  |
+--------------------------------+-----------------------------------------------------------------------------------+
| ``nodes\lists\SetClauseList``  | instances of ``nodes\SingleSetClause`` or ``nodes\MultipleSetClause``             |
+--------------------------------+-----------------------------------------------------------------------------------+
| ``nodes\lists\SetTargetList``  | instances of ``nodes\SetTargetElement``                                           |
+--------------------------------+-----------------------------------------------------------------------------------+
| ``nodes\lists\TargetList``     | instances of ``nodes\TargetElement`` or ``nodes\Star``                            |
+--------------------------------+-----------------------------------------------------------------------------------+
| ``nodes\lists\WindowList``     | instances of ``nodes\WindowDefinition``                                           |
+--------------------------------+-----------------------------------------------------------------------------------+
| ``nodes\WithClause``           | instances of ``nodes\CommonTableExpression``                                      |
+--------------------------------+-----------------------------------------------------------------------------------+
