========
Overview
========

As this package contains a reimplementation of PostgreSQL's query parser, it is a bit different
from the usual breed of "write-only" query builders:

- Query is represented by an Abstract Syntax Tree consisting of ``Node``\ s.
  This is quite similar to what Postgres does internally.
- When building a query, it is possible to start with manually written SQL and work from there.
- Query parts (e.g. new columns for a ``SELECT`` or parts of a ``WHERE`` clause) can usually be added to the AST
  either as ``Node``\ s or as strings. Strings are processed by ``Parser``, so query being built is
  automatically checked for correct syntax.
- Nodes can be removed and replaced in AST (e.g. calling ``join()`` method of a node in ``FROM`` clause
  replaces it with a ``JoinExpression`` node having the original node as its left argument).
- AST can be analyzed and transformed, the package takes advantage of this to allow named parameters like ``:foo``
  instead of standard PostgreSQL's positional parameters ``$1`` and to infer parameters' types from SQL typecasts.

Requirements
============

**pg_builder** requires at least PHP 8.2 with `ctype <https://www.php.net/manual/en/book.ctype.php>`__
extension (it is usually installed and enabled by default).

Minimum supported PostgreSQL version is 12.

The ultimate goal of any query builder is to execute the built queries, this can be done using
either `native pgsql extension <https://php.net/manual/en/book.pgsql.php>`__ with
`pg_wrapper <https://github.com/sad-spirit/pg-wrapper>`__ package
or `PDO <https://www.php.net/manual/en/book.pdo.php>`__.

Substantial effort was made to optimise parsing, but not parsing is faster anyway, so it is highly recommended to use
`PSR-6 <https://www.php-fig.org/psr/psr-6/>`__ compatible cache in production for caching the parts of AST and / or
the complete queries.

Installation
============

Require the package with `composer <https://getcomposer.org/>`__:

.. code-block:: bash

    composer require "sad_spirit/pg_builder:^3"


Related packages
================

`sad_spirit/pg_wrapper <https://github.com/sad-spirit/pg-wrapper>`__
  - Converter of `PostgreSQL data types <https://www.postgresql.org/docs/current/datatype.html>`__ to their PHP
    equivalents and back and
  - An object oriented wrapper around PHP's native `pgsql extension <https://php.net/manual/en/book.pgsql.php>`__.

`sad_spirit/pg_gateway <https://github.com/sad-spirit/pg-gateway>`__
  Builds upon pg_wrapper and pg_builder to provide
  `Table Data Gateway <https://martinfowler.com/eaaCatalog/tableDataGateway.html>`__ implementation
  for Postgres that

  - Reads table metadata from database and uses that to help with generating queries and to automatically convert
    PHP variables used for query parameters;
  - Combines queries generated via several gateways through ``WITH`` / ``JOIN`` / ``EXISTS()``;
  - Provides a means to transparently cache the complete generated statements.
