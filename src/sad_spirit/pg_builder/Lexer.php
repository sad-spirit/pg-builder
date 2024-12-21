<?php

/**
 * Query builder for Postgres backed by SQL parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder;

/**
 * Scans an SQL string for tokens
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
     * @var array<string, string>
     * @link https://www.postgresql.org/docs/current/sql-syntax-lexical.html#SQL-SYNTAX-STRINGS-ESCAPE
     */
    private static $replacements = [
        'b'  => "\10", // backspace, no equivalent in PHP
        'f'  => "\f",
        'n'  => "\n",
        'r'  => "\r",
        't'  => "\t"
    ];

    /** @var string */
    private $source;
    /** @var int */
    private $position;
    /** @var int */
    private $length;
    /** @var Token[] */
    private $tokens;
    /** @var array<string,mixed> */
    private $options;
    /**
     * Set to true if Unicode escapes were seen during main lexer run, triggers processing these
     * @var bool
     */
    private $unescape;
    /**
     * First codepoint of Unicode surrogate pair
     * @var int|null
     */
    private $pairFirst;
    /**
     * Position of last Unicode escape in string
     * @var int
     */
    private $lastUnicodeEscape;

    /** @var array<string, int> */
    private $operatorCharHash;
    /** @var array<string, int> */
    private $specialCharHash;
    /** @var array<string, int> */
    private $nonStandardCharHash;
    /** @var string */
    private $baseRegexp;

    /**
     * Constructor, sets options for lexer
     *
     * Possible options are
     *  'standard_conforming_strings' - the same meaning as the postgresql.conf parameter. When true (default),
     *      then backslashes in '...' strings are treated literally, when false they are treated as escape
     *      characters.
     *
     * @param array<string, mixed> $options
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

    # positional parameter or dollar-quoted string, groups 2, 3 (junk test), 4
    \\$(?: ( \d+ ) ( [A-Za-z\\x80-\\xFF_] )? | ( (?: [A-Za-z\\x80-\\xFF_][A-Za-z\\x80-\\xFF_0-9]*)? )\\$ ) |

    # typecast, named function argument or named parameter, group 5 
    : (?: = | : | ( [A-Za-z\\x80-\\xFF_][A-Za-z\\x80-\\xFF_0-9]* ) ) |

    # numeric constant, groups 6, 7 (junk test)
    ( (?: \d+ (?: \.\d+|\.(?!\.))? | \.\d+) (?: [eE][+-]\d+ )? ( [A-Za-z\\x80-\\xFF_] )? ) |

    [uU]&["'] |                     # string/identifier with unicode escapes
    \.\. |                          # double dot (error outside of PL/PgSQL)
    ([{$quotedSpecialChars}]) |     # everything that looks, well, special, group 8
    
    ( [A-Za-z\\x80-\\xff_][A-Za-z\\x80-\\xff_0-9$]* ) # identifier, obviously, group 9
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
     *
     * @psalm-suppress RedundantFunctionCall
     */
    public function tokenize(string $sql): TokenStream
    {
        if (extension_loaded('mbstring') && (2 & (int)ini_get('mbstring.func_overload'))) {
            throw new exceptions\RuntimeException(
                'Multibyte function overloading must be disabled for correct parser operation'
            );
        }

        $this->source   = $sql;
        $this->position = strspn($this->source, " \r\n\t\f", 0);
        $this->length   = strlen($sql);
        $this->tokens   = [];
        $this->unescape = false;

        $this->doTokenize();

        $this->tokens[] = new Token(Token::TYPE_EOF, '', $this->position);

        if (!$this->unescape) {
            return new TokenStream($this->tokens, $this->source);
        } else {
            $this->unescapeUnicodeTokens();
            return new TokenStream(array_values($this->tokens), $this->source);
        }
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
            if (isset($m[9])) {
                // ASCII-only downcasing
                $lowCase = strtr($m[9], 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
                if (isset(Keywords::LIST[$lowCase])) {
                    $this->tokens[] = new Token(Keywords::LIST[$lowCase][0], $lowCase, $this->position);
                } else {
                    $this->tokens[] = new Token(Token::TYPE_IDENTIFIER, $lowCase, $this->position);
                }
                $this->position += strlen($m[9]);

            } elseif (isset($m[8])) {
                if (isset($this->operatorCharHash[$m[8]])) {
                    $this->lexOperator();
                } else {
                    $this->tokens[] = new Token(Token::TYPE_SPECIAL_CHAR, $m[8], $this->position++);
                }

            } elseif (isset($m[6])) {
                if (isset($m[7])) {
                    throw exceptions\SyntaxException::atPosition(
                        "Trailing junk after numeric literal: '$m[0]'",
                        $this->source,
                        $this->position
                    );
                }
                $this->tokens[] = new Token(
                    ctype_digit($m[6]) ? Token::TYPE_INTEGER : Token::TYPE_FLOAT,
                    $m[6],
                    $this->position
                );
                $this->position += strlen($m[6]);

            } elseif (isset($m[5])) {
                $this->tokens[] = new Token(Token::TYPE_NAMED_PARAM, $m[5], $this->position);
                $this->position += strlen($m[0]);

            } elseif (isset($m[4])) {
                $this->lexDollarQuoted($m[0]);

            } elseif (isset($m[2])) {
                if (isset($m[3])) {
                    throw exceptions\SyntaxException::atPosition(
                        "Trailing junk after positional parameter: '$m[0]'",
                        $this->source,
                        $this->position
                    );
                }
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

                    case 'u&"':
                    case 'U&"':
                        $this->unescape = true;
                        $this->lexDoubleQuoted(true);
                        break;

                    case "u&'":
                    case "U&'":
                        if (!$this->options['standard_conforming_strings']) {
                            throw exceptions\SyntaxException::atPosition(
                                "String constants with Unicode escapes cannot be used "
                                . "when standard_conforming_strings is off.",
                                $this->source,
                                $this->position
                            );
                        }
                        $this->unescape = true;
                        $this->lexString('u&');
                        break;

                    default:
                        // should not reach this
                        throw exceptions\SyntaxException::atPosition(
                            "Unexpected '{$m[0]}'",
                            $this->source,
                            $this->position
                        );
                }
            }

            // skip whitespace
            $this->position += strspn($this->source, " \r\n\t\f", $this->position);
        }
    }

    /**
     * Processes a double-quoted identifier
     *
     * @param bool $unicode Whether this is a u&"..." identifier
     * @throws exceptions\SyntaxException
     */
    private function lexDoubleQuoted(bool $unicode = false): void
    {
        $skip = $unicode ? 2 : 0;
        if (!preg_match('/" ( (?>[^"]+ | "")* ) "/Ax', $this->source, $m, 0, $this->position + $skip)) {
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
        $this->tokens[] = new Token(
            $unicode ? Token::TYPE_UNICODE_IDENTIFIER : Token::TYPE_IDENTIFIER,
            strtr($m[1], ['""' => '"']),
            $this->position
        );
        $this->position += $skip + strlen($m[0]);
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
     * @param string $prefix Characters before quote that define string type, one of 'b', 'e', 'x', 'n', 'u&'
     * @throws exceptions\SyntaxException
     */
    private function lexString(string $prefix = ''): void
    {
        $realPosition   = $this->position;
        $type           = Token::TYPE_STRING;
        $regexNoQuotes  = "'[^']*'";
        $regexSlashes   = "' ( (?>[^'\\\\]+ | '' | \\\\.)* ) '";
        $regexNoSlashes = "' ( (?>[^']+ | '')* ) '";
        switch ($prefix) {
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

            case 'u&':
                $regex = $regexNoSlashes;
                $type  = Token::TYPE_UNICODE_STRING;
                break;

            /** @noinspection PhpMissingBreakStatementInspection */
            case 'n':
                $type  = Token::TYPE_NCHAR_STRING;
                // fall-through is intentional here

            default:
                $regex = $this->options['standard_conforming_strings'] ? $regexNoSlashes : $regexSlashes;
        }

        if (!preg_match("/{$regex}/Ax", $this->source, $m, 0, $this->position + strlen($prefix))) {
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
                $value .= $this->unescapeCStyle($m[1], $this->position + strlen($prefix));
            }
            $this->position += strlen($prefix) + strlen($m[0]);
            $prefix          = '';
        } while (preg_match("/{$concat}/Ax", $this->source, $m, 0, $this->position));

        $this->tokens[] = new Token($type, $value, $realPosition);
    }

    /**
     * Replaces escape sequences in string constants with C-style escapes
     *
     * @param string $escaped String constant without delimiters
     * @param int $position Position of string constant in source SQL (for exceptions)
     * @return string String with escape sequences replaced
     */
    private function unescapeCStyle(string $escaped, int $position): string
    {
        $this->pairFirst         = null;
        $this->lastUnicodeEscape = 0;

        $unescaped  = '';

        foreach (
            preg_split(
                "!(\\\\(?:x[0-9a-fA-F]{1,2}|[0-7]{1,3}|u[0-9a-fA-F]{0,4}|U[0-9a-fA-F]{0,8}|[^0-7]))!",
                $escaped,
                -1,
                PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE
            ) ?: [] as [$part, $partPosition]
        ) {
            if ('\\' !== $part[0]) {
                if (null !== $this->pairFirst) {
                    break;
                }
                $unescaped .= strtr($part, ["''" => "'"]);

            } elseif ('u' !== $part[1] && 'U' !== $part[1]) {
                if (null !== $this->pairFirst) {
                    break;
                }

                if (isset(self::$replacements[$part[1]])) {
                    $unescaped .= self::$replacements[$part[1]];

                } elseif ('x' === $part[1]) {
                    $unescaped .= chr((int)hexdec(substr($part, 2)));

                } elseif (strspn($part, '01234567', 1)) {
                    $unescaped .= chr((int)octdec(substr($part, 1)));

                } else {
                    // Just strip the escape and return the following char, as Postgres does
                    $unescaped .= $part[1];
                }

            } else {
                $expected = 'u' === $part[1] ? 6 : 10;
                if ($expected > strlen($part)) {
                    throw exceptions\SyntaxException::atPosition(
                        'Invalid Unicode escape value',
                        $this->source,
                        $position + $partPosition
                    );
                }

                $unescaped .= $this->handlePossibleSurrogatePairs(
                    (int)hexdec(substr($part, 2)),
                    $partPosition,
                    $position
                );
            }
        }

        if (null !== $this->pairFirst) {
            throw exceptions\SyntaxException::atPosition(
                'Unfinished Unicode surrogate pair',
                $this->source,
                $position + $this->lastUnicodeEscape
            );
        }

        return $unescaped;
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
        throw new exceptions\InvalidArgumentException('Invalid Unicode codepoint');
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

    /**
     * Processes Unicode escapes in u&'...' string literals and u&"..." identifiers
     *
     * Handles possible trailing UESCAPE clauses, replaces TYPE_UNICODE_* tokens with simple
     * TYPE_STRING and TYPE_IDENTIFIER ones having Unicode escapes in their values replaced by UTF-8 representations.
     * Similar to what base_yylex() function in PostgreSQL's /src/backend/parser/parser.c does.
     */
    private function unescapeUnicodeTokens(): void
    {
        for ($i = 0, $tokenCount = count($this->tokens); $i < $tokenCount; $i++) {
            $tokenType = $this->tokens[$i]->getType();
            if (Token::TYPE_UNICODE_STRING !== $tokenType && Token::TYPE_UNICODE_IDENTIFIER !== $tokenType) {
                continue;
            }

            if (!($hasUescape = $this->tokens[$i + 1]->matches(Token::TYPE_KEYWORD, 'uescape'))) {
                $newValue = $this->unescapeUnicode($this->tokens[$i]);
            } else {
                if (Token::TYPE_STRING !== $this->tokens[$i + 2]->getType()) {
                    throw exceptions\SyntaxException::atPosition(
                        'UESCAPE must be followed by a simple string literal',
                        $this->source,
                        $this->tokens[$i + 2]->getPosition()
                    );
                }

                $escape = $this->tokens[$i + 2]->getValue();
                if (
                    1 !== strlen($escape)
                    || ctype_xdigit($escape)
                    || ctype_space($escape)
                    || in_array($escape, ['"', "'", '+'], true)
                ) {
                    throw exceptions\SyntaxException::atPosition(
                        'Invalid Unicode escape character',
                        $this->source,
                        $this->tokens[$i + 2]->getPosition()
                    );
                }

                $newValue = $this->unescapeUnicode($this->tokens[$i], $escape);
            }

            $this->tokens[$i] = new Token(
                Token::TYPE_UNICODE_STRING === $tokenType ? Token::TYPE_STRING : Token::TYPE_IDENTIFIER,
                $newValue,
                $this->tokens[$i]->getPosition()
            );

            // Skip and remove uescape tokens, they aren't needed in TokenStream
            if ($hasUescape) {
                unset($this->tokens[$i + 1], $this->tokens[$i + 2]);
                $i += 2;
            }
        }
    }

    /**
     * Replaces Unicode escapes in values of string or identifier Tokens
     *
     * This is a bit more complex than replaceEscapeSequences() as the escapes may specify UTF-16 surrogate pairs
     * that should be combined into single codepoint.
     *
     * @param Token  $token      The whole Token is needed to be able to use its position in exception messages
     * @param string $escapeChar Value of escape character provided by UESCAPE 'X' clause, if present
     * @return string value of Token with Unicode escapes replaced by their UTF-8 equivalents
     */
    private function unescapeUnicode(Token $token, string $escapeChar = '\\'): string
    {
        $this->pairFirst         = null;
        $this->lastUnicodeEscape = 0;

        $unescaped = '';
        $quoted    = preg_quote($escapeChar);

        foreach (
            preg_split(
                "/({$quoted}(?:{$quoted}|[0-9a-fA-F]{4}|\\+[0-9a-fA-F]{6}))/",
                $token->getValue(),
                -1,
                PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_DELIM_CAPTURE
            ) ?: [] as [$part, $position]
        ) {
            if ($escapeChar === $part[0] && $escapeChar !== $part[1]) {
                $unescaped .= $this->handlePossibleSurrogatePairs(
                    (int)hexdec(ltrim($part, $escapeChar . '+')),
                    $position,
                    $token->getPosition() + 3
                );

            } elseif (null !== $this->pairFirst) {
                break;

            } elseif ($escapeChar === $part[0]) {
                $unescaped .= $escapeChar;

            } elseif (false !== ($escapePos = strpos($part, $escapeChar))) {
                throw exceptions\SyntaxException::atPosition(
                    "Invalid Unicode escape",
                    $this->source,
                    $token->getPosition() + 3 + $position + $escapePos
                );

            } else {
                $unescaped .= $part;
            }
        }

        if (null !== $this->pairFirst) {
            throw exceptions\SyntaxException::atPosition(
                'Unfinished Unicode surrogate pair',
                $this->source,
                $token->getPosition() + 3 + $this->lastUnicodeEscape
            );
        }

        return $unescaped;
    }

    /**
     * Converts a Unicode code point that can be a part of surrogate pair to its UTF-8 encoded representation
     *
     * @param int $codepoint
     * @param int $position
     * @param int $basePosition
     * @return string
     * @throws exceptions\SyntaxException
     */
    private function handlePossibleSurrogatePairs(int $codepoint, int $position, int $basePosition): string
    {
        $utf8              = '';
        $isSurrogateFirst  = 0xD800 <= $codepoint && 0xDBFF >= $codepoint;
        $isSurrogateSecond = 0xDC00 <= $codepoint && 0xDFFF >= $codepoint;
        try {
            if ((null !== $this->pairFirst) !== $isSurrogateSecond) {
                throw exceptions\SyntaxException::atPosition(
                    "Invalid Unicode surrogate pair",
                    $this->source,
                    $basePosition + (null !== $this->pairFirst ? $this->lastUnicodeEscape : $position)
                );
            } elseif (null !== $this->pairFirst) {
                $utf8 = self::codePointToUtf8(
                    (($this->pairFirst & 0x3FF) << 10) + 0x10000 + ($codepoint & 0x3FF)
                );
                $this->pairFirst = null;
            } elseif ($isSurrogateFirst) {
                $this->pairFirst = $codepoint;
            } else {
                $utf8 = self::codePointToUtf8($codepoint);
            }

        } catch (exceptions\InvalidArgumentException $e) {
            throw exceptions\SyntaxException::atPosition(
                $e->getMessage(),
                $this->source,
                $basePosition + $position
            );
        }

        $this->lastUnicodeEscape = $position;

        return $utf8;
    }
}
