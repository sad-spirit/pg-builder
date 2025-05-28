.. _parser-internals:

================================
Internals: Parser implementation
================================

The SQL string is first processed by ``Lexer`` and converted to ``TokenStream`` object aggregating implementations
of ``Token``. ``Parser`` then goes over that stream and builds the Abstract Syntax Tree composed of
``Node`` implementations.

``Lexer`` class
===============

The class is based on flex lexer defined in ``src/backend/parser/scan.l`` file of Postgres sources.

``Lexer`` does not create ``Token``\ s for whitespace and comments. It also does some preprocessing: unescapes
strings and identifiers that used Unicode escapes and removes ``UESCAPE`` clauses.

``TokenType`` enum
==================

This is an int-backed enum containing possible types for ``Token``\ s. The backing values are bitmasks that can be used
for checking that the concrete type matches a generic one

.. code-block:: php

    if (0 !== ($token->getType()->value & TokenType::PARAMETER->value)) {
        echo "Token represents a parameter placeholder";
    }

Tokens can only have a concrete type rather than a generic one (with the notable exception of
``TokenType::IDENTIFIER``), additionally ``TokenType::UNICODE_STRING`` and ``TokenType::UNICODE_IDENTIFIER`` are
only used inside ``Lexer``.

``Keyword`` enum
================

This is a string-backed enum containing the list of all keywords for the most recent Postgres version.
It is generated from ``src/include/parser/kwlist.h`` file.

It has two methods corresponding to additional keyword properties from the above file:

``getType(): TokenType``
    Returns a case of ``TokenType`` representing the category of keyword. Postgres has a lot of keywords, but most
    of these may be used as identifiers without the need to quote them.

    The case returned will always be a "subtype" of generic ``TokenType::KEYWORD``.

``isBareLabel(): bool``
    Returns whether the keyword may be used as column alias in ``SELECT`` statement / ``RETURNING`` clause
    without the ``AS`` keyword.

``Token`` interface and its implementations
===========================================

The ``Token`` interface represents a token that has knowledge of its type, value and position in input string.

.. code-block:: php

    namespace sad_spirit\pg_builder;

    interface Token extends \Stringable
    {
        public function matches(TokenType $type, string|string[]|null $values = null) : bool;
        public function matchesAnyKeyword(Keyword ...$keywords): bool;

        public function getPosition() : int;
        public function getType() : TokenType;
        public function getKeyword() : ?Keyword;
        public function getValue() : string;
    }

``matches()``
    Checks whether current token matches given type and/or value. ``$type`` is matched like a bitmask (see above) and
    then value is checked against given ``$values``.

``matchesAnyKeyword()``
    Checks whether current token matches any of the given keywords. This can only return ``true`` if the token
    represents a keyword (e.g. is an instance of ``KeywordToken``).

The following implementations of ``Token`` are available:

``tokens\EOFToken``
    Represents end of input.

``tokens\KeywordToken``
    Represents a keyword. This returns a non-``null`` value from ``getKeyword()`` and may return ``true``
    from ``matchesAnyKeyword()``.

``tokens\StringToken``
    Token defined by a type and a string value. E.g. token with type ``TokenType::STRING`` and ``foo`` value represents
    literal ``'foo'`` while the one with ``TokenType::IDENTIFIER`` and ``foo`` value represents identifier ``foo``.

``TokenStream``
===============

This class represents a stream of ``Token``\ s.

.. code-block:: php

    namespace sad_spirit\pg_builder;

    class TokenStream implements \Stringable
    {
        // Movement within stream
        public function next() : Token;
        public function skip(int $number) : void;
        public function isEOF() : bool;
        public function getCurrent() : Token;
        public function look(int $number = 1) : Token;
        public function reset() : void;

        // These map to methods of current Token
        public function matches(TokenType $type, string|string[]|null $values = null) : bool;
        public function getKeyword() : ?Keyword;
        public function matchesAnyKeyword(Keyword ...$keywords): ?Keyword;

        // Wrappers for common matches() cases
        public function matchesSpecialChar(string|string[] $char) : bool;
        public function matchesAnyType(TokenType ...$types) : bool;
        public function matchesKeywordSequence(Keyword|Keyword[] ...$keywords): bool

        // These throw SyntaxException if the current Token does not match the given values
        public function expect(TokenType $type, string|string[]|null $values = null): Token;
        public function expectKeyword(Keyword ...$keywords) : Keyword;
    }

``Token`` implementations and ``TokenStream`` implement magic ``__toString()`` method
allowing easy debug output:

.. code-block:: php

   use sad_spirit\pg_builder\Lexer;

   $lexer = new Lexer();
   echo $lexer->tokenize('select * from some_table');

yields

.. code-block:: output

   keyword 'select' at position 0
   special character '*' at position 7
   keyword 'from' at position 9
   identifier 'some_table' at position 14
   end of input

``Parser``
==========

This is a LL(\*) recursive descent parser. It tries to closely follow a part of bison grammar defined
in ``src/backend/parser/gram.y`` file of Postgres sources, but the implementation is completely independent.

.. note::

    The part that is reimplemented starts around the ``PreparableStmt`` production in ``gram.y``.

Differences from Postgres parser: the following constructs are not
supported

- ``TABLE name`` alias for ``SELECT * FROM name``
- ``SELECT INTO``
- ``WHERE CURRENT OF cursor`` for ``UPDATE`` and ``DELETE`` queries
- Undocumented ``TREAT()`` function
