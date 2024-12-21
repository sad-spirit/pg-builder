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

namespace sad_spirit\pg_builder\tests;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\{
    Lexer,
    Token,
    exceptions\InvalidArgumentException,
    exceptions\SyntaxException
};
use sad_spirit\pg_builder\nodes\expressions\{
    Constant,
    NumericConstant
};

/**
 * Unit test for query lexer
 */
class LexerTest extends TestCase
{
    /**
     * @var Lexer
     */
    protected $lexer;

    protected function setUp(): void
    {
        $this->lexer = new Lexer();
    }

    public function testTokenTypes(): void
    {
        $stream = $this->lexer->tokenize("sElEcT 'select' \"select\", FOO + 1.2 - 3., 4 ! <> :foo, $1::integer");

        $stream->expect(Token::TYPE_KEYWORD, 'select');
        $stream->expect(Token::TYPE_STRING, 'select');
        $stream->expect(Token::TYPE_IDENTIFIER, 'select');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
        $stream->expect(Token::TYPE_IDENTIFIER, 'foo');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, '+');
        $stream->expect(Token::TYPE_FLOAT, '1.2');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, '-');
        $stream->expect(Token::TYPE_FLOAT, '3.');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
        $stream->expect(Token::TYPE_INTEGER, '4');
        $stream->expect(Token::TYPE_OPERATOR, '!');
        $stream->expect(Token::TYPE_INEQUALITY, '<>');
        $stream->expect(Token::TYPE_NAMED_PARAM, 'foo');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
        $stream->expect(Token::TYPE_POSITIONAL_PARAM, '1');
        $stream->expect(Token::TYPE_TYPECAST, '::');
        $stream->expect(Token::TYPE_KEYWORD, 'integer');
        $this->assertTrue($stream->isEOF());
    }

    public function testStripComments(): void
    {
        $stream = $this->lexer->tokenize(<<<QRY
select FOO -- this is a one-line comment
, bar /* this is
a multiline C-style comment */, "bA""z" /*
this is a /* nested C-style */ comment */
as quux -- another comment
QRY
        );
        $stream->expect(Token::TYPE_KEYWORD, 'select');
        $stream->expect(Token::TYPE_IDENTIFIER, 'foo');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
        $stream->expect(Token::TYPE_IDENTIFIER, 'bar');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
        $stream->expect(Token::TYPE_IDENTIFIER, 'bA"z');
        $stream->expect(Token::TYPE_KEYWORD, 'as');
        $stream->expect(Token::TYPE_IDENTIFIER, 'quux');
        $this->assertTrue($stream->isEOF());
    }

    /**
     * @dataProvider getConcatenatedStrings
     * @param string $sql
     * @param array $tokens
     */
    public function testConcatenateStringLiterals(string $sql, array $tokens): void
    {
        $stream = $this->lexer->tokenize($sql);
        foreach ($tokens as $token) {
            $this->assertEquals($token, $stream->next()->getValue());
        }
    }

    public function getConcatenatedStrings(): array
    {
        return [
            [
                <<<QRY
'foo'
    'bar' -- a comment
'baz'
QRY
                , ['foobarbaz']
            ],
            [
                <<<QRY
'foo' /*
    a multiline comment
    */
    'bar'-- a comment with no whitespace
'baz'
 
  
   'quux'
QRY
                , ['foo', 'barbazquux']
            ],
            [
                "'foo'\t\f\r'bar'",
                ['foobar']
            ],
            [
                "'foo'--'bar'",
                ['foo']
            ]
        ];
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testMulticharacterOperators(): void
    {
        $stream = $this->lexer->tokenize(<<<QRY
#!/*--
*/=- @+ <=
+* *+--/*
!=-
QRY
        );
        $stream->expect(Token::TYPE_OPERATOR, '#!');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, '=');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, '-');
        $stream->expect(Token::TYPE_OPERATOR, '@+');
        $stream->expect(Token::TYPE_INEQUALITY, '<=');
        $stream->expect(Token::TYPE_OPERATOR, '+*');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, '*');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, '+');
        $stream->expect(Token::TYPE_OPERATOR, '!=-');
    }

    public function testStandardConformingStrings(): void
    {
        $string = " 'foo\\\\bar' e'foo\\\\bar' ";
        $stream = $this->lexer->tokenize($string);
        $this->assertEquals('foo\\\\bar', $stream->next()->getValue());
        $this->assertEquals('foo\\bar', $stream->next()->getValue());

        $this->lexer = new Lexer(['standard_conforming_strings' => false]);
        $stream2 = $this->lexer->tokenize($string);
        $this->assertEquals('foo\\bar', $stream2->next()->getValue());
        $this->assertEquals('foo\\bar', $stream2->next()->getValue());
    }

    /**
     * @param string $sql
     * @param string $expected
     * @dataProvider validCStyleEscapesProvider
     */
    public function testValidCStyleEscapes(string $sql, string $expected): void
    {
        $stream = $this->lexer->tokenize($sql);
        $this::assertEquals($expected, $stream->next()->getValue());
    }

    /**
     * @param string $sql
     * @param string $message
     * @dataProvider invalidCStyleEscapesProvider
     */
    public function testInvalidCStyleEscapes(string $sql, string $message): void
    {
        $this::expectException(SyntaxException::class);
        $this::expectExceptionMessage($message);
        $this->lexer->tokenize($sql);
    }

    public function testDollarQuotedString(): void
    {
        $stream = $this->lexer->tokenize(<<<QRY
    $\$a string$$
    \$foo$ another $$ string ' \\ \$foo$
QRY
        );
        $this->assertEquals('a string', $stream->next()->getValue());
        $this->assertEquals(' another $$ string \' \\ ', $stream->next()->getValue());
    }

    public function testUnterminatedCStyleComment(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unterminated /* comment');
        $this->lexer->tokenize('/* foo');
    }

    public function testUnterminatedQuotedIdentifier(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unterminated quoted identifier');
        $this->lexer->tokenize('update "foo ');
    }

    public function testZeroLengthQuotedIdentifier(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Zero-length quoted identifier');
        $this->lexer->tokenize('select "" as foo');
    }

    public function testUnterminatedDollarQuotedString(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unterminated dollar-quoted string');
        $this->lexer->tokenize('select $foo$ blah $$ blah');
    }

    /**
     * @dataProvider getUnterminatedLiterals
     * @param string $literal
     */
    public function testUnterminatedStringLiteral(string $literal): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unterminated string literal');
        $this->lexer->tokenize($literal);
    }

    public function getUnterminatedLiterals(): array
    {
        return [
            ["select 'foo  "],
            [" 'foo \\' '"], // standards_conforming_string is on by default
            [" e'foo "],
            [" e'foo\\'"],
            [" x'1234"]
        ];
    }

    public function testUnexpectedSymbol(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Unexpected '{'");
        $this->lexer->tokenize('select foo{bar}');
    }

    public function testNonAsciiIdentifiers(): void
    {
        $stream = $this->lexer->tokenize('ИмЯ_бЕз_КаВыЧеК "ИмЯ_в_КаВыЧкАх" $ыыы$строка в долларах$ыыы$ :параметр');

        $stream->expect(Token::TYPE_IDENTIFIER, 'ИмЯ_бЕз_КаВыЧеК');
        $stream->expect(Token::TYPE_IDENTIFIER, 'ИмЯ_в_КаВыЧкАх');
        $stream->expect(Token::TYPE_STRING, 'строка в долларах');
        $stream->expect(Token::TYPE_NAMED_PARAM, 'параметр');
        $this->assertTrue($stream->isEOF());
    }

    public function testDisallowStringsWithUnicodeEscapesWhenStandardConformingStringsIsOff(): void
    {
        $lexer = new Lexer(['standard_conforming_strings' => false]);

        // Identifiers should be allowed anyway
        $stream = $lexer->tokenize('U&"allowed"');
        $stream->expect(Token::TYPE_IDENTIFIER, 'allowed');
        $this::assertTrue($stream->isEOF());

        // Strings should fail
        $this::expectException(SyntaxException::class);
        $this::expectExceptionMessage('standard_conforming_strings');
        $lexer->tokenize("u&'not allowed'");
    }

    /**
     * @param string $sql
     * @param int $type
     * @param string $value
     * @dataProvider validUnicodeEscapesProvider
     */
    public function testValidUnicodeEscapes(string $sql, int $type, string $value): void
    {
        $stream = $this->lexer->tokenize($sql);
        $stream->expect($type, $value);
        $this::assertTrue($stream->isEOF());
    }

    /**
     * @param string $sql
     * @param string $message
     * @dataProvider invalidUnicodeEscapesProvider
     */
    public function testInvalidUnicodeEscapes(string $sql, string $message): void
    {
        $this::expectException(SyntaxException::class);
        $this::expectExceptionMessage($message);
        $this->lexer->tokenize($sql);
    }

    /**
     * @param string $sql
     * @dataProvider invalidUTF8Provider
     */
    public function testDisallowInvalidUTF8(string $sql): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Invalid UTF-8');
        $this->lexer->tokenize($sql);
    }

    /**
     * @param string $sql
     * @dataProvider invalidNumericLiteralProvider
     */
    public function testDisallowJunkAfterNumericLiterals(string $sql): void
    {
        $this::expectException(SyntaxException::class);
        $this::expectExceptionMessage("Trailing junk");
        $this->lexer->tokenize($sql);
    }

    public function testDisallowJunkAfterPositionalParameters(): void
    {
        $this::expectException(SyntaxException::class);
        $this::expectExceptionMessage("Trailing junk");
        $this->lexer->tokenize('$1a');
    }

    public function testNumberAndDoubleDot(): void
    {
        $this::expectException(SyntaxException::class);
        $this::expectExceptionMessage("Unexpected '..'");
        $this->lexer->tokenize('1..2');
    }

    /**
     * @param string $sql
     * @dataProvider validNumericLiteralProvider
     */
    public function testAllowNonDecimalNumericLiteralsAndUnderscores(string $sql): void
    {
        $stream = $this->lexer->tokenize($sql);
        $this::assertInstanceOf(
            NumericConstant::class,
            Constant::createFromToken($stream->next())
        );
    }

    public function validCStyleEscapesProvider(): array
    {
        return [
            ["e''''",                                                                     "'"],
            ["e'\\'\\''",                                                                 "''"],

            ["e'\\n \\t\\z'",                                                             "\n \tz"],

            ["e'\\xd0\\274\\320\\xbe\\320\\273\\xd0\\276\\320\\xb4\\320\\276\\xd0\\271'", 'молодой'],
            ["e'\\u0441\\u043b\\u043e\\u043d\\U0000043e\\u043a'",                         'слонок'],
            ["e'\\xd0\\275\\320\\xbe\\321\\201\\xd0\\260\\321\\x82\\321\\213\\xd0\\271'", 'носатый'],
            ["e'\\uD83D\\uDE01 \\U0000D83D\\U0000DE2C'",                                  '😁 😬']
        ];
    }

    public function invalidCStyleEscapesProvider(): array
    {
        return [
            ["e'wrong: \\u061'",                    "Invalid Unicode escape"],
            ["e'wrong: \\U0061'",                   "Invalid Unicode escape"],
            ["e'wrong: \\udb99'",                   'Unfinished Unicode surrogate pair'],
            ["e'wrong: \\udb99xy'",                 'Unfinished Unicode surrogate pair'],
            ["e'wrong: \\udb99\\\\'",               'Unfinished Unicode surrogate pair'],
            ["e'wrong: \\udb99\\u0061'",            'Invalid Unicode surrogate pair'],
            ["e'wrong: \\U0000db99\\U00000061'",    'Invalid Unicode surrogate pair'],
            ["e'wrong: \\U002FFFFF'",               'Invalid Unicode codepoint']
        ];
    }

    public function validUnicodeEscapesProvider(): array
    {
        return [
            ["U&'d\\0061t\\+000061'", Token::TYPE_STRING, 'data'],
            ['U&"d\\0061t\\+000061"', Token::TYPE_IDENTIFIER, 'data'],
            ["U&'d!0061t\\+000061' UESCAPE '!'", Token::TYPE_STRING, 'dat\\+000061'],
            ['U&"d*0061t\\+000061" UESCAPE \'*\'', Token::TYPE_IDENTIFIER, 'dat\\+000061'],
            ["U&'a\\\\b'", Token::TYPE_STRING, 'a\\b'],
            ["U&' \\' UESCAPE '!'", Token::TYPE_STRING, ' \\'],
            ["U&'\\D83D\\DE01 \\+00D83D\\+00DE2C'", Token::TYPE_STRING, '😁 😬']
        ];
    }

    public function invalidUnicodeEscapesProvider(): array
    {
        return [
            ["U&'wrong: \\061'", "Invalid Unicode escape"],
            ["U&'wrong: \\+0061'", "Invalid Unicode escape"],
            ["U&'wrong: +0061' UESCAPE +", 'UESCAPE must be followed by a simple string literal'],
            ["U&'wrong: +0061' UESCAPE '+'", 'Invalid Unicode escape character'],
            ["U&'wrong: \\db99'", 'Unfinished Unicode surrogate pair'],
            ["U&'wrong: \\db99xy'", 'Unfinished Unicode surrogate pair'],
            ["U&'wrong: \\db99\\\\'", 'Unfinished Unicode surrogate pair'],
            ["U&'wrong: \\db99\\0061'", 'Invalid Unicode surrogate pair'],
            ["U&'wrong: \\+00db99\\+000061'", 'Invalid Unicode surrogate pair'],
            ["U&'wrong: \+2FFFFF'", 'Invalid Unicode codepoint']
        ];
    }

    public function invalidUTF8Provider(): array
    {
        return [
            ["e'\\xf0'"],
            ["'\xf0'"],
            ["n'\xf0'"],
            ["u&'\xf0'"],
            ['u&"' . chr(240) . '"'],
            [':' . chr(240)],
            ['"ident' . chr(240) . 'ifier"'],
            ['ident' . chr(240) . 'ifier']
        ];
    }

    /**
     * From src\test\regress\sql\numerology.sql
     * @return array
     */
    public function invalidNumericLiteralProvider(): array
    {
        return [
            ['123abc'],
            ['0x0o'],
            ['0.a'],
            ['0.0a'],
            ['.0a'],
            ['0.0e1a'],
            ['0.0e'],
            ['0.0e+a'],

            ['0b'],
            ['1b'],
            ['0b0x'],

            ['0o'],
            ['1o'],
            ['0o0x'],

            ['0x'],
            ['1x'],
            ['0x0y'],
            ['100_'],
            ['100__000'],

            ['1_000_.5'],
            ['1_000._5'],
            ['1_000.5_'],
            ['1_000.5e_1']
        ];
    }

    /**
     * From src\test\regress\sql\numerology.sql
     * @return array
     */
    public function validNumericLiteralProvider(): array
    {
        return [
            ['0b100101'],
            ['0o273'],
            ['0x42F'],
            ['1_000_000'],
            ['1_2_3'],
            ['0x1EEE_FFFF'],
            ['0o2_73'],
            ['0b_10_0101'],

            ['1_000.000_005'],
            ['1_000.'],
            ['.000_005'],
            ['1_000.5e0_1']
        ];
    }
}
