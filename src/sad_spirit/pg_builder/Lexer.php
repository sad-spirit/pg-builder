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

declare(strict_types=1);

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
    public const CHARS_OPERATOR = '~!@#^&|`?+-*/%<>=';

    /**
     * Characters that should be returned as Token::TYPE_SPECIAL_CHAR
     */
    private const CHARS_SPECIAL  = ',()[].;:+-*/%^<>=';

    /**
     * Replacements for simple backslash escapes in e'...' strings
     * @var array
     * @link https://www.postgresql.org/docs/current/sql-syntax-lexical.html#SQL-SYNTAX-STRINGS-ESCAPE
     */
    private static $replacements = [
        'b'  => "\10", // backspace, no equivalent in PHP
        'f'  => "\f",
        'n'  => "\n",
        'r'  => "\r",
        't'  => "\t"
    ];

    private $source;
    private $position;
    private $length;
    private $tokens;
    private $options;

    private $operatorCharHash;
    private $specialCharHash;
    private $nonStandardCharHash;
    private $baseRegexp;

    /**
     * Constructor, sets options for lexer
     *
     * Possible options are
     *  'standard_conforming_strings' - the same meaning as the postgresql.conf parameter. When true (default),
     *      then backslashes in '...' strings are treated literally, when false they are treated as escape
     *      characters.
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge(
            ['standard_conforming_strings' => true],
            $options
        );

        $this->operatorCharHash     = array_flip(str_split(self::CHARS_OPERATOR));
        $this->specialCharHash      = array_flip(str_split(self::CHARS_SPECIAL));
        $this->nonStandardCharHash  = array_flip(str_split('~!@#^&|`?%'));

        $uniqueSpecialChars         = array_unique(str_split(self::CHARS_SPECIAL . self::CHARS_OPERATOR));
        $quotedSpecialChars         = preg_quote(implode('', $uniqueSpecialChars));
        $this->baseRegexp           = <<<REGEXP
{ 
    --  |                           # start of single-line comment
    /\* |                           # start of multi-line comment
    '   |                           # string literal
    "   |                           # double-quoted identifier
    ([bBeEnNxX])' |                 # string literal with prefix, group 1

    # positional parameter or dollar-quoted string, groups 2, 3
    \\$(?: ( \d+ ) | ( (?: [A-Za-z\\x80-\\xFF_][A-Za-z\\x80-\\xFF_0-9]*)? )\\$ ) |

    # typecast, named function argument or named parameter, group 4 
    : (?: = | : | ( [A-Za-z\\x80-\\xFF_][A-Za-z\\x80-\\xFF_0-9]* ) ) |

    # numeric constant, group 5
    ( (?: \d+ (?: \.\d+|\.(?!.))? | \.\d+) (?: [eE][+-]\d+ )? ) |

    [uU]&["'] |                     # (currently unsupported) unicode escapes
    \.\. |                          # double dot (error outside of PL/PgSQL)
    ([{$quotedSpecialChars}]) |     # everything that looks, well, special, group 6
    
    ( [A-Za-z\\x80-\\xff_][A-Za-z\\x80-\\xff_0-9$]* ) # identifier, obviously, group 7
}Ax
REGEXP;
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

        $this->tokens[] = new Token(Token::TYPE_EOF, '', $this->position);

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
            if (!preg_match($this->baseRegexp, $this->source, $m, 0, $this->position)) {
                throw exceptions\SyntaxException::atPosition(
                    "Unexpected '{$this->source[$this->position]}'",
                    $this->source,
                    $this->position
                );
            }

            // check for found subpatterns first
            if (isset($m[7])) {
                // ASCII-only downcasing
                $lowCase = strtr($m[7], 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
                if (isset(Keywords::LIST[$lowCase])) {
                    $this->tokens[] = new Token(Keywords::LIST[$lowCase], $lowCase, $this->position);
                } else {
                    $this->tokens[] = new Token(Token::TYPE_IDENTIFIER, $lowCase, $this->position);
                }
                $this->position += strlen($m[7]);

            } elseif (isset($m[6])) {
                if (isset($this->operatorCharHash[$m[6]])) {
                    $this->lexOperator();
                } else {
                    $this->tokens[] = new Token(Token::TYPE_SPECIAL_CHAR, $m[6], $this->position++);
                }

            } elseif (isset($m[5])) {
                $this->tokens[] = new Token(
                    ctype_digit($m[5]) ? Token::TYPE_INTEGER : Token::TYPE_FLOAT,
                    $m[5],
                    $this->position
                );
                $this->position += strlen($m[5]);

            } elseif (isset($m[4])) {
                $this->tokens[] = new Token(Token::TYPE_NAMED_PARAM, $m[4], $this->position);
                $this->position += strlen($m[0]);

            } elseif (isset($m[3])) {
                $this->lexDollarQuoted($m[0]);

            } elseif (isset($m[2])) {
                $this->tokens[] = new Token(Token::TYPE_POSITIONAL_PARAM, $m[2], $this->position);
                $this->position += strlen($m[0]);

            } elseif (isset($m[1])) {
                $this->lexString(strtolower($m[1]));

            } else {
                // now check the complete match
                switch ($m[0]) {
                    case '--':
                        // single line comment, skip until newline
                        $this->position += strcspn($this->source, "\r\n", $this->position);
                        break;

                    case '/*':
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
                        break;

                    case "'":
                        $this->lexString();
                        break;

                    case '"':
                        $this->lexDoubleQuoted();
                        break;

                    case '::':
                        $this->tokens[] = new Token(Token::TYPE_TYPECAST, '::', $this->position);
                        $this->position += 2;
                        break;

                    case ':=':
                        $this->tokens[] = new Token(Token::TYPE_COLON_EQUALS, ':=', $this->position);
                        $this->position += 2;
                        break;

                    case '..':
                        throw exceptions\SyntaxException::atPosition(
                            "Unexpected '..'",
                            $this->source,
                            $this->position
                        );

                    default:
                        if ('u' === $m[0][0] || 'U' === $m[0][0]) {
                            throw new exceptions\NotImplementedException(
                                'Support for unicode escapes not implemented yet'
                            );
                        } else {
                            // should not reach this
                            throw exceptions\SyntaxException::atPosition(
                                "Unexpected '{$m[0]}'",
                                $this->source,
                                $this->position
                            );
                        }
                }
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
        $this->tokens[] = new Token(Token::TYPE_IDENTIFIER, strtr($m[1], ['""' => '"']), $this->position);
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
        $this->tokens[] = new Token(
            Token::TYPE_STRING,
            substr($this->source, $this->position + $delimiterLength, $pos - $this->position - $delimiterLength),
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
                try {
                    $value .= strtr(self::replaceEscapeSequences($m[1]), ["''" => "'"]);
                } catch (exceptions\SyntaxException $e) {
                    throw exceptions\SyntaxException::atPosition($e->getMessage(), $this->source, $this->position);
                }
            }
            $this->position += ('' === $char ? 0 : 1) + strlen($m[0]);
            $char            = '';
        } while (preg_match("/{$concat}/Ax", $this->source, $m, 0, $this->position));

        $this->tokens[] = new Token($type, $value, $realPosition);
    }

    /**
     * Replaces escape sequences in string constants with C-style escapes
     *
     * @param string $escaped String constant without quotes
     * @return string String with escape sequences replaced
     */
    private static function replaceEscapeSequences(string $escaped): string
    {
        return preg_replace_callback(
            '!\\\\(x[0-9a-fA-F]{1,2}|[0-7]{1,3}|u[0-9a-fA-F]{0,4}|U[0-9a-fA-F]{0,8}|[^0-7])!',
            function ($matches) {
                $sequence = $matches[1];

                if (isset(self::$replacements[$sequence])) {
                    return self::$replacements[$sequence];

                } elseif ('x' === $sequence[0]) {
                    return chr(hexdec(substr($sequence, 1)));

                } elseif ('u' === $sequence[0] || 'U' === $sequence[0]) {
                    $expected = 'u' === $sequence[0] ? 5 : 9;
                    if ($expected > strlen($sequence)) {
                        throw new exceptions\SyntaxException('invalid Unicode escape value ' . $matches[0]);
                    }
                    return self::codePointToUtf8(hexdec(substr($sequence, 1)));

                } elseif (strspn($sequence, '01234567')) {
                    return chr(octdec($sequence));

                } else {
                    // Just strip the escape and return the following char, as Postgres does
                    return $sequence;
                }
            },
            $escaped
        );
    }

    /**
     * Converts a Unicode code point to its UTF-8 encoded representation.
     *
     * Borrowed from nikic/php-parser
     *
     * @param int $codepoint Code point
     * @return string UTF-8 representation of code point
     */
    private static function codePointToUtf8(int $codepoint): string
    {
        if ($codepoint <= 0x7F) {
            return chr($codepoint);
        }
        if ($codepoint <= 0x7FF) {
            return chr(($codepoint >> 6) + 0xC0) . chr(($codepoint & 0x3F) + 0x80);
        }
        if ($codepoint <= 0xFFFF) {
            return chr(($codepoint >> 12) + 0xE0)
                   . chr((($codepoint >> 6) & 0x3F) + 0x80)
                   . chr(($codepoint & 0x3F) + 0x80);
        }
        if ($codepoint <= 0x1FFFFF) {
            return chr(($codepoint >> 18) + 0xF0)
                   . chr((($codepoint >> 12) & 0x3F) + 0x80)
                   . chr((($codepoint >> 6) & 0x3F) + 0x80)
                   . chr(($codepoint & 0x3F) + 0x80);
        }
        throw new exceptions\SyntaxException('Invalid Unicode escape sequence: Codepoint too large');
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
            $this->tokens[] = new Token(Token::TYPE_SPECIAL_CHAR, $operator, $this->position++);
            return;
        }
        if (2 === $length) {
            if ('=' === $operator[0] && '>' === $operator[1]) {
                $this->tokens[] = new Token(Token::TYPE_EQUALS_GREATER, '=>', $this->position);
                $this->position += 2;
                return;
            }
            if (
                '=' === $operator[1] && ('<' === $operator[0] || '>' === $operator[0] || '!' === $operator[0])
                || '>' === $operator[1] && '<' === $operator[0]
            ) {
                $this->tokens[] = new Token(Token::TYPE_INEQUALITY, $operator, $this->position);
                $this->position += 2;
                return;
            }
        }

        $this->tokens[] = new Token(Token::TYPE_OPERATOR, $operator, $this->position);
        $this->position += $length;
    }
}
