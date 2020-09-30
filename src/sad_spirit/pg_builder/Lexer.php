<?php

/**
 * Query builder for PostgreSQL backed by a query parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder;

/**
 * Scans an SQL string for tokens
 *
 * @todo Support for Unicode escapes in strings U&'...' and identifiers U&"..."
 */
class Lexer
{
    /**
     * Characters that may appear in operators
     */
    private const CHARS_OPERATOR = '~!@#^&|`?+-*/%<>=';

    /**
     * Characters that should be returned as Token::TYPE_SPECIAL_CHAR
     */
    private const CHARS_SPECIAL  = ',()[].;:+-*/%^<>=';

    private $source;
    private $position;
    private $length;
    private $tokens;
    private $options;

    private $operatorCharHash;
    private $specialCharHash;
    private $stringPrefixCharHash;
    private $nonStandardCharHash;

    /**
     * Stores a token in the array
     *
     * @param string $value     Token value
     * @param int    $type      Token type, one of Token::TYPE_* constants
     * @param int    $position  Position in input
     */
    protected function pushToken($value, $type, $position)
    {
        $this->tokens[] = new Token($type, $value, $position);
    }

    /**
     * Constructor, sets options for lexer
     *
     * Possible options are
     *  'standard_conforming_strings' - the same meaning as the postgresql.conf parameter. When true (default),
     *      then backslashes in '...' strings are treated literally, when false they are treated as escape
     *      characters.
     *  'ascii_only_downcasing' - when true only ASCII letters in unquoted identifiers will be converted to
     *      lower case, when false all letters (according to current mbstring encoding / locale). Only should
     *      be set to false for single-byte encodings (see changelog for Postgres releases @ 2013-10-10)!
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->options  = array_merge([
            'standard_conforming_strings' => true,
            'ascii_only_downcasing'       => true
        ], $options);

        $this->operatorCharHash     = array_flip(str_split(self::CHARS_OPERATOR));
        $this->specialCharHash      = array_flip(str_split(self::CHARS_SPECIAL));
        $this->stringPrefixCharHash = array_flip(str_split('bBeEnNxX'));
        $this->nonStandardCharHash  = array_flip(str_split('~!@#^&|`?%'));
    }

    /**
     * Tokenizes the input string
     *
     * @param string $sql
     * @return TokenStream
     * @throws exceptions\NotImplementedException
     * @throws exceptions\SyntaxException
     */
    public function tokenize(string $sql): TokenStream
    {
        if (extension_loaded('mbstring') && (2 & ini_get('mbstring.func_overload'))) {
            throw new exceptions\RuntimeException(
                'Multibyte function overloading must be disabled for correct parser operation'
            );
        }

        $this->source   = $sql;
        $this->position = strspn($this->source, " \r\n\t\f", 0);
        $this->length   = strlen($sql);
        $this->tokens   = [];

        $this->doTokenize();

        $this->pushToken('', Token::TYPE_EOF, $this->position);

        return new TokenStream($this->tokens, $this->source);
    }

    /**
     * Does the actual tokenizing work
     *
     * @throws exceptions\SyntaxException
     * @throws exceptions\NotImplementedException
     */
    private function doTokenize(): void
    {
        while ($this->position < $this->length) {
            $char     = $this->source[$this->position];
            $nextChar = $this->position + 1 < $this->length ? $this->source[$this->position + 1] : null;
            if ('-' === $char && '-' === $nextChar) {
                // single line comment, skip until newline
                $this->position += strcspn($this->source, "\r\n", $this->position);

            } elseif ('/' === $char && '*' === $nextChar) {
                // multiline comment, skip
                if (
                    !preg_match(
                        '!/\* ( (?>[^/*]+ | /[^*] | \*[^/] ) | (?R) )* \*/!Ax',
                        $this->source,
                        $m,
                        0,
                        $this->position
                    )
                ) {
                    throw exceptions\SyntaxException::atPosition(
                        'Unterminated /* comment',
                        $this->source,
                        $this->position
                    );
                }
                $this->position += strlen($m[0]);

            } elseif ('"' === $char) {
                $this->lexDoubleQuoted();

            } elseif (isset($this->stringPrefixCharHash[$char]) && "'" === $nextChar) {
                $this->lexString(strtolower($char));

            } elseif ("'" === $char) {
                $this->lexString();

            } elseif ('$' === $char) {
                if (
                    preg_match(
                        '/\$([A-Za-z\x80-\xFF_][A-Za-z\x80-\xFF_0-9]*)?\$/A',
                        $this->source,
                        $m,
                        0,
                        $this->position
                    )
                ) {
                    $this->lexDollarQuoted($m[0]);

                } elseif (preg_match('/\$(\d+)/A', $this->source, $m, 0, $this->position)) {
                    $this->pushToken($m[1], Token::TYPE_POSITIONAL_PARAM, $this->position);
                    $this->position += strlen($m[0]);

                } else {
                    throw exceptions\SyntaxException::atPosition("Unexpected '\$'", $this->source, $this->position);
                }


            } elseif (':' === $char) {
                if (':' === $nextChar) {
                    $this->pushToken('::', Token::TYPE_TYPECAST, $this->position);
                    $this->position += 2;
                } elseif ('=' === $nextChar) {
                    $this->pushToken(':=', Token::TYPE_COLON_EQUALS, $this->position);
                    $this->position += 2;
                } elseif (
                    preg_match(
                        '/:([A-Za-z\x80-\xFF_][A-Za-z\x80-\xFF_0-9]*)/A',
                        $this->source,
                        $m,
                        0,
                        $this->position
                    )
                ) {
                    $this->pushToken($m[1], Token::TYPE_NAMED_PARAM, $this->position);
                    $this->position += strlen($m[0]);
                } else {
                    $this->pushToken(':', Token::TYPE_SPECIAL_CHAR, $this->position++);
                }

            } elseif ('.' === $char) {
                if ('.' === $nextChar) {
                    // Double dot is used only in PL/PgSQL. We don't parse that.
                    throw exceptions\SyntaxException::atPosition("Unexpected '..'", $this->source, $this->position);
                } elseif (ctype_digit($nextChar)) {
                    $this->lexNumeric();
                } else {
                    $this->pushToken('.', Token::TYPE_SPECIAL_CHAR, $this->position++);
                }

            } elseif (ctype_digit($char)) {
                $this->lexNumeric();

            } elseif (isset($this->operatorCharHash[$char])) {
                $this->lexOperator();

            } elseif (isset($this->specialCharHash[$char])) {
                $this->pushToken($char, Token::TYPE_SPECIAL_CHAR, $this->position++);

            } elseif (
                ('u' === $char || 'U' === $char)
                      && preg_match('/[uU]&["\']/A', $this->source, $m, 0, $this->position)
            ) {
                throw new exceptions\NotImplementedException('Support for unicode escapes not implemented yet');

            } else {
                $this->lexIdentifier();
            }

            // skip whitespace
            $this->position += strspn($this->source, " \r\n\t\f", $this->position);
        }
    }

    /**
     * Processes a double-quoted identifier
     *
     * @throws exceptions\SyntaxException
     */
    private function lexDoubleQuoted(): void
    {
        if (!preg_match('/" ( (?>[^"]+ | "")* ) "/Ax', $this->source, $m, 0, $this->position)) {
            throw exceptions\SyntaxException::atPosition(
                'Unterminated quoted identifier',
                $this->source,
                $this->position
            );
        } elseif ('' === $m[1]) {
            throw exceptions\SyntaxException::atPosition(
                'Zero-length quoted identifier',
                $this->source,
                $this->position
            );
        }
        $this->pushToken(strtr($m[1], ['""' => '"']), Token::TYPE_IDENTIFIER, $this->position);
        $this->position += strlen($m[0]);
    }

    /**
     * Processes a dollar-quoted string
     *
     * @param string $delimiter
     * @throws exceptions\SyntaxException
     */
    private function lexDollarQuoted(string $delimiter): void
    {
        $delimiterLength = strlen($delimiter);
        if (false === ($pos = strpos($this->source, $delimiter, $this->position + $delimiterLength))) {
            throw exceptions\SyntaxException::atPosition(
                'Unterminated dollar-quoted string',
                $this->source,
                $this->position
            );
        }
        $this->pushToken(
            substr($this->source, $this->position + $delimiterLength, $pos - $this->position - $delimiterLength),
            Token::TYPE_STRING,
            $this->position
        );
        $this->position = $pos + $delimiterLength;
    }

    /**
     * Processes a single-quoted literal
     *
     * @param string $char Character before quote that defines string type, one of 'b', 'e', 'x', 'n'
     * @throws exceptions\SyntaxException
     */
    private function lexString(string $char = ''): void
    {
        $realPosition   = $this->position;
        $type           = Token::TYPE_STRING;
        $regexNoQuotes  = "'[^']*'";
        $regexSlashes   = "' ( (?>[^'\\\\]+ | '' | \\\\.)* ) '";
        $regexNoSlashes = "' ( (?>[^']+ | '')* ) '";
        switch ($char) {
            case 'b':
                $type  = Token::TYPE_BINARY_STRING;
                $regex = $regexNoQuotes;
                break;

            case 'e':
                $regex = $regexSlashes;
                break;

            case 'x':
                $regex = $regexNoQuotes;
                $type  = Token::TYPE_HEX_STRING;
                break;

            /** @noinspection PhpMissingBreakStatementInspection */
            case 'n':
                $type  = Token::TYPE_NCHAR_STRING;
                // fall-through is intentional here

            default:
                $regex = $this->options['standard_conforming_strings'] ? $regexNoSlashes : $regexSlashes;
        }

        if (!preg_match("/{$regex}/Ax", $this->source, $m, 0, $this->position + ('' === $char ? 0 : 1))) {
            throw exceptions\SyntaxException::atPosition('Unterminated string literal', $this->source, $this->position);
        }

        $value  = '';
        $concat = '[ \t\f]* (?: --[^\n\r]* )? [\n\r] (?> [ \t\n\r\f]+ | --[^\n\r]*[\n\r] )*' . $regex;
        do {
            if ($regex === $regexNoQuotes) {
                $value .= $m[1];
            } elseif ($regex === $regexNoSlashes) {
                $value .= strtr($m[1], ["''" => "'"]);
            } else {
                $value .= strtr(stripcslashes($m[1]), ["''" => "'"]);
            }
            $this->position += ('' === $char ? 0 : 1) + strlen($m[0]);
            $char            = '';
        } while (preg_match("/{$concat}/Ax", $this->source, $m, 0, $this->position));

        $this->pushToken($value, $type, $realPosition);
    }

    /**
     * Processes a numeric literal
     */
    private function lexNumeric(): void
    {
        // this will always match as lexNumeric is called on either 'digit' or '.digit'
        preg_match(
            '/(\d+ (\.\d+|\.(?!.))? | \.\d+) ( [eE][+-]\d+ )?/Ax',
            $this->source,
            $m,
            0,
            $this->position
        );
        if (ctype_digit($m[0])) {
            $this->pushToken($m[0], Token::TYPE_INTEGER, $this->position);
        } else {
            $this->pushToken($m[0], Token::TYPE_FLOAT, $this->position);
        }
        $this->position += strlen($m[0]);
    }

    /**
     * Processes an operator
     */
    private function lexOperator(): void
    {
        $length   = strspn($this->source, self::CHARS_OPERATOR, $this->position);
        $operator = substr($this->source, $this->position, $length);
        if ($commentSingle = strpos($operator, '--')) {
            $length = min($length, $commentSingle);
        }
        if ($commentMulti = strpos($operator, '/*')) {
            $length = min($length, $commentMulti);
        }
        if (
            $length > 1
            && ('+' === $operator[$length - 1] || '-' === $operator[$length - 1])
        ) {
            for ($i = $length - 2; $i >= 0; $i--) {
                if (isset($this->nonStandardCharHash[$operator[$i]])) {
                    break;
                }
            }
            if ($i < 0) {
                do {
                    $length--;
                } while (
                    $length > 1
                         && ('+' === $operator[$length - 1] || '-' === $operator[$length - 1])
                );
            }
        }

        $operator = substr($operator, 0, $length);
        if (1 === $length && isset($this->specialCharHash[$operator])) {
            $this->pushToken($operator, Token::TYPE_SPECIAL_CHAR, $this->position++);
            return;
        }
        if (2 === $length) {
            if ('=' === $operator[0] && '>' === $operator[1]) {
                $this->pushToken('=>', Token::TYPE_EQUALS_GREATER, $this->position);
                $this->position += 2;
                return;
            }
            if (
                '=' === $operator[1] && ('<' === $operator[0] || '>' === $operator[0] || '!' === $operator[0])
                || '>' === $operator[1] && '<' === $operator[0]
            ) {
                $this->pushToken($operator, Token::TYPE_INEQUALITY, $this->position);
                $this->position += 2;
                return;
            }
        }

        $this->pushToken($operator, Token::TYPE_OPERATOR, $this->position);
        $this->position += $length;
    }

    /**
     * Processes an identifier or a keyword
     *
     * @throws exceptions\SyntaxException
     */
    private function lexIdentifier(): void
    {
        if (
            !preg_match(
                '/[A-Za-z\x80-\xff_][A-Za-z\x80-\xff_0-9\$]*/A',
                $this->source,
                $m,
                0,
                $this->position
            )
        ) {
            throw exceptions\SyntaxException::atPosition(
                "Unexpected '{$this->source[$this->position]}'",
                $this->source,
                $this->position
            );
        }

        // ASCII-only downcasing
        $lowcase = strtr($m[0], 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
        if (isset(Keywords::LIST[$lowcase])) {
            $this->pushToken($lowcase, Keywords::LIST[$lowcase], $this->position);

        } else {
            if (!$this->options['ascii_only_downcasing']) {
                if (extension_loaded('mbstring')) {
                    $lowcase = mb_strtolower($lowcase);
                } else {
                    $lowcase = strtolower($lowcase);
                }
            }
            $this->pushToken($lowcase, Token::TYPE_IDENTIFIER, $this->position);
        }

        $this->position += strlen($m[0]);
    }
}
