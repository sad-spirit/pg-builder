=====================
sad_spirit/pg_builder
=====================

This is a query builder for Postgres backed by a partial PHP reimplementation of PostgreSQL's own query parser.
All syntax available for ``SELECT`` (and ``VALUES``), ``INSERT``, ``UPDATE``, ``DELETE``, and ``MERGE``
queries in Postgres 17 is supported with minor omissions.

.. toctree::
   :maxdepth: 3
   :caption: Contents:

   overview
   statement-factory
   statements
   base
   helpers
   parsing
