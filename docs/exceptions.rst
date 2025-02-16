=================
Exception classes
=================

The package contains base exception interface ``\sad_spirit\pg_builder\Exception`` and several specialized exception
classes that extend `SPL Exception classes <https://php.net/manual/en/spl.exceptions.php>`__ and implement
this interface. Therefore all exceptions thrown in **pg_builder** can be caught with

.. code-block:: php

   use sad_spirit\pg_builder\Exception;

   try {
       // Do some query parsing and building
   } catch (Exception $e) {
       // Handle exception
   }

All exception classes belong to ``sad_spirit\pg_builder\exceptions``
namespace:

``BadMethodCallException extends \BadMethodCallException``
    Namespaced version of SPL's BadMethodCallException. Thrown by ``Parser::__call()`` for unavailable methods.

``InvalidArgumentException extends \InvalidArgumentException``
    Namespaced version of SPL's InvalidArgumentException.

``RuntimeException extends \RuntimeException``
    Namespaced version of SPL's RuntimeException.

        ``NotImplementedException extends RuntimeException``
            Thrown for not-quite-ready features.

``SyntaxException extends \DomainException``
    Thrown for parsing failures. This is the most common exception you'll get when using **pg_builder**.
