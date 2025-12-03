.. _queries:

===========================
Executing the built queries
===========================

While **pg_builder** can be used on its own, the main goal of building an SQL statement is to eventually execute
it on the database server. **pg_wrapper** package provides a means to execute queries and to convert PHP variables
to Postgres representation. **pg_builder** on the other hand is able to infer a proper database type
for such conversions directly from SQL.

It is also possible to execute the built queries with PDO, though this requires more boilerplate for manual
type conversions and may still need **pg_wrapper** as a dependency.

.. _queries-nativestatement:

``NativeStatement`` class
=========================

While it is possible to just use :ref:`SqlBuilderWalker <walkers-sql>` for converting a Statement to string

.. code-block:: php

    $sql = $statement->dispatch(new SqlBuilderWalker());

the recommended way is to use :ref:`StatementFactory::createFromAST() <statement-factory-conversion>` which
returns an instance of ``NativeStatement``.

In addition to generated SQL, it contains mapping from named parameters to positional ones and info on parameter types
extracted from query:

.. code-block:: php

   use sad_spirit\pg_builder\StatementFactory;

   $factory = new StatementFactory();
   $native  = $factory->createFromAST($factory->createFromString(
       'select typname from pg_catalog.pg_type where oid = any(:oid::integer[]) order by typname'
   ));

   echo $native->getSql() . "\n\n";
   var_dump($native->getNamedParameterMap());
   echo "\n";
   var_dump($native->getParameterTypes());

will output something like

.. code-block:: output

    select typname
    from pg_catalog.pg_type
    where oid = any($1::pg_catalog.int4[])
    order by typname

    array(1) {
      ["oid"]=>
      int(0)
    }

    array(1) {
      [0]=>
      object(sad_spirit\pg_builder\nodes\TypeName)#610 (7) {
        ...
      }
    }

Public API of ``NativeStatement``:

.. code-block:: php

    namespace sad_spirit\pg_builder;

    use sad_spirit\pg_wrapper\{
        Connection,
        PreparedStatement,
        Result
    };

    final class NativeStatement
    {
        public function __construct(
            private readonly string $sql,
            private readonly array<int, ?nodes\TypeName> $parameterTypes,
            private readonly array<string, int> $namedParameterMap
        );

        // Serialization helper
        public function __serialize() : array

        // getters for properties
        public function getSql() : string;
        public function getNamedParameterMap() : array<string, int>;
        public function getParameterTypes() : array<int, ?nodes\TypeName>;

        // helper methods for parameters
        public function mapNamedParameters(array<string, mixed> $parameters) : array<int, mixed>;
        public function mergeParameterTypes(array $paramTypes) : array<int, mixed>;

        // query execution using Connection class from pg_wrapper
        public function executeParams(
            Connection $connection,
            array $params,
            array $paramTypes = [],
            array $resultTypes = []
        ) : Result;
        public function prepare(
            Connection $connection,
            array $paramTypes = [],
            array $resultTypes = []
        ) : PreparedStatement;
        public function executePrepared(array $params = []) : Result;
    }

.. note::
    The class is tagged ``@final`` in version 3.3 and will be declared ``final`` in the next major release.

The helper methods use mappings shown above to convert / update parameters and parameter types:

``mapNamedParameters()``
    Converts parameters array keyed with parameters' names to a list of parameters.
    Will throw ``InvalidArgumentException`` in case of missing or unknown parameter names.

``mergeParameterTypes()``
    Merges the types array received from builder with additional types info. ``$inputTypes`` can be keyed by
    either names or positions, type specifications from this array take precedence over types received
    from builder. Will throw ``InvalidArgumentException`` in case of invalid keys.

It is rarely needed to call the above methods directly as query execution methods do that themselves.

.. _queries-types:

Converting types for query parameters
=====================================

Type conversion itself is implemented in **pg_wrapper** package, additional code is only needed to make it
understand types represented by ``nodes\TypeName``. These are extracted from typecasts in SQL strings:
``foo::bar`` or ``cast(foo as bar)``.

Additionally, a convenience ``convertParameters()`` method allows batch-converting parameters for
``\PDOStatement::execute()``.

``converters\TypeNameNodeHandler`` interface
--------------------------------------------

.. note::
    This interface will likely be changed to extend ``ConfigurableTypeConverterFactory`` in the next major release,
    so implement that if you are creating a custom implementation of ``TypeNameNodeHandler``.

This interface extends ``TypeConverterFactory`` from
`sad_spirit/pg_wrapper <https://github.com/sad-spirit/pg-wrapper>`__ package
and defines methods for working with ``TypeName`` nodes

.. code-block:: php

    namespace sad_spirit\pg_builder\converters;

    use sad_spirit\pg_builder\nodes\TypeName;
    use sad_spirit\pg_wrapper\TypeConverter;
    use sad_spirit\pg_wrapper\TypeConverterFactory;

    interface TypeNameNodeHandler extends TypeConverterFactory
    {
        public function getConverterForTypeNameNode(TypeName $typeName) : TypeConverter;
        public function createTypeNameNodeForOID(int|numeric-string $oid) : TypeName;
    }

``getConverterForTypeNameNode()``
    This method should be called from ``getConverterForTypeSpecification()`` when it receives a ``TypeName`` as
    an argument. Usually that ``TypeName`` will be extracted by ``ParameterWalker``
    from the typecast node within query AST.

``createTypeNameNodeForOID()``
    This method can be used when building queries to add explicit typecasts for columns based on table metadata.
    It is used that way throughout `sad_spirit/pg_gateway <https://github.com/sad-spirit/pg-gateway>`__ package.

``converters\BuilderSupportDecorator`` class
--------------------------------------------

This class implements the above interface and decorates an implementation of ``ConfigurableTypeConverterFactory``
interface from `sad_spirit/pg_wrapper <https://github.com/sad-spirit/pg-wrapper>`__ package
(this implementation will most likely be an instance of ``DefaultTypeConverterFactory``).

.. code-block:: php

    namespace sad_spirit\pg_builder\converters;

    class BuilderSupportDecorator implements ConfigurableTypeConverterFactory, TypeNameNodeHandler
    {
        public function __construct(
            private readonly ConfigurableTypeConverterFactory $wrapped,
            private readonly Parser $parser
        );

        // Convenience method for PDO
        public function convertParameters(
            NativeStatement $statement,
            array<string, mixed> $parameters,
            array<string, mixed> $paramTypes = []
        ) : array<string, ?string>;

        // Methods from TypeNameNodeHandler omitted
        // Methods from ConfigurableTypeConverterFactory omitted
    }

A ``Parser`` instance passed to the constructor is used to parse type names provided as strings instead of
the implementation in ``$wrapped``. This way any type name understood by Postgres itself will be understood by
``BuilderSupportDecorator``.

``getConverterForTypeSpecification()`` accepts instances of ``nodes\TypeName`` in addition to what
``$wrapped`` itself accepts.

Finally, ``convertParameters()`` is used to generate database string representations of PHP values, these can be
passed to ``\PDOStatement::execute()``.

Executing queries using pg_wrapper
==================================

If the built query does not contain any parameters executing it is trivial:

.. code-block:: php

    $result = $connection->execute($native->getSql());

If the query uses parameters, the easiest way would be to call methods of ``NativeStatement``. The first step,
however, is setting up type conversion so that :ref:`type names extracted from AST could be processed <queries-types>`:

.. code-block:: php

    use sad_spirit\pg_builder\{
        StatementFactory,
        converters\BuilderSupportDecorator
    };
    use sad_spirit\pg_wrapper\Connection;

    $connection = new Connection('...');
    // ... $connection configuration goes here ...

    // Uses DB connection properties to set up parsing and building of SQL, reuses metadata cache if available
    $factory    = StatementFactory::forConnection($connection);
    // It is also possible to create $factory manually
    // $factory = new StatementFactory(...);
    // Decorate the DefaultTypeConverterFactory so that it processes TypeName nodes
    $connection->setTypeConverterFactory(new BuilderSupportDecorator(
        $connection->getTypeConverterFactory(),
        $factory->getParser()
    ));

After that is done, execute queries using named parameters and relying on types specified in the query:

.. code-block:: php

   $native  = $factory->createFromAST($factory->createFromString(
       'select typname from pg_catalog.pg_type where oid = any(:oid::integer[]) order by typname'
   ));

    foreach ($native->executeParams($connection, ['oid' => [21, 23]])->iterateColumn('typname') as $type) {
        echo $type . "\n";
    }

    $native->prepare($connection);
    foreach ($native->executePrepared(['oid' => [16, 114]])->iterateColumn('typname') as $type) {
        echo $type . "\n";
    }

outputting

.. code-block:: output

    int2
    int4
    bool
    json

Executing queries using PDO
===========================

As above, executing the query that does not use parameters is trivial:

.. code-block:: php

    $result = $pdo->query($native->getSql());

If, however, you need to convert parameters for a query having ones, this should be done manually using
``BuilderSupportDecorator::convertParameters()``. Create an instance of that class first

.. code-block:: php

    use sad_spirit\pg_builder\{
        StatementFactory,
        converters\BuilderSupportDecorator
    };
    use sad_spirit\pg_wrapper\{
        Connection,
        converters\DefaultTypeConverterFactory
    };

    $pdo       = new \PDO('pgsql:...');
    // Uses DB connection properties to set up parsing and building of SQL
    $factory   = StatementFactory::forPDO($pdo);
    // It is also possible to create $factory manually, but make sure to enable $PDOCompatible
    // $factory = new StatementFactory(...);
    // You still need pg_wrapper as a dependency for DefaultTypeConverterFactory class
    $converter = new BuilderSupportDecorator(new DefaultTypeConverterFactory(), $factory->getParser());

After that, assuming the same code to generate ``$native``, the query can be executed this way:

.. code-block:: php

    $stmt = $pdo->prepare($native->getSql());
    $stmt->execute($converter->convertParameters(
        $native,
        ['oid' => [21, 23]]
    ));

    while (false !== $type = $stmt->fetchColumn(0)) {
        echo $type . "\n";
    }

outputting, obviously

.. code-block:: output

    int2
    int4

.. _queries-nativestatement-caching:

Caching ``NativeStatement`` instances
=====================================

.. note::

    Caching whole statements makes sense when you use parameters.
    If you just build query with constants caching won't help much

    .. code-block:: php

       // This is OK:
       $ast->where->and('foo_id = any(:id::integer[])');
       // ...sometime later...
       $query->executeParams($connection, ['id' => $ids]);

       // This is not OK:
       $ast->where->and('foo_id in (' . implode(', ', $ids) . ')');

``NativeStatement`` is designed with caching in mind and implements ``__serialize()`` serialization helper.

The main issue with caching the complete statement is generating the cache key: it should not depend on generated
SQL as this defeats the whole idea but should uniquely identify that statement.

The suggested approach is to assign keys to the query parts and then generate statement key based on these.

.. code-block:: php

   // You need to know the structure of query beforehand to create a cache key
   $queryParts = [
       'base' => 'baseQueryId'
       'foo'  => '...',
       'bar'  => '...'
   ];

   $cacheKey   = 'query-' . md5(serialize($queryParts));
   $cacheItem  = $cache->getItem($cacheKey);
   if ($cacheItem->isHit()) {
       $query = $cacheItem->get();

   } else {
       $ast = createBaseQuery($queryParts['base']);
       if (!empty($queryParts['foo'])) {
           $ast->list[] = 'foo.*'
           $ast->from[0]->join('foo')->using = ['foo_id'];
       }
       if (!empty($queryParts['bar'])) {
           // ...
       }
       // ...

       $query = $factory->createFromAST($ast);
       $cache->save($cacheItem->set($query);
   }

`sad_spirit/pg_gateway package <https://github.com/sad-spirit/pg-gateway>`__ uses the above approach which usually
allows skipping the whole parse / build process for the queries.
