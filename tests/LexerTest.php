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
 * @copyright 2014-2018 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\tests;

use sad_spirit\pg_builder\Lexer,
    sad_spirit\pg_builder\Token;

/**
 * Unit test for query lexer
 */
class LexerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Lexer
     */
    protected $lexer;

    public function setUp()
    {
        $this->lexer = new Lexer();
    }

    public function testTokenTypes()
    {
        $stream = $this->lexer->tokenize("sElEcT 'select' \"select\", FOO + 1.2, 3 ! <> :foo, $1::integer");

        $stream->expect(Token::TYPE_KEYWORD, 'select');
        $stream->expect(Token::TYPE_STRING, 'select');
        $stream->expect(Token::TYPE_IDENTIFIER, 'select');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
        $stream->expect(Token::TYPE_IDENTIFIER, 'foo');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, '+');
        $stream->expect(Token::TYPE_FLOAT, '1.2');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
        $stream->expect(Token::TYPE_INTEGER, '3');
        $stream->expect(Token::TYPE_OPERATOR, '!');
        $stream->expect(Token::TYPE_INEQUALITY, '<>');
        $stream->expect(Token::TYPE_NAMED_PARAM, 'foo');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
        $stream->expect(Token::TYPE_POSITIONAL_PARAM, '1');
        $stream->expect(Token::TYPE_TYPECAST, '::');
        $stream->expect(Token::TYPE_KEYWORD, 'integer');
        $this->assertTrue($stream->isEOF());
    }

    public function testStripComments()
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

    public function testConcatenateStringLiterals()
    {
        $stream = $this->lexer->tokenize(<<<QRY
'foo'
    'bar' -- a comment
'baz'
QRY
);
        $this->assertEquals('foobarbaz', $stream->next()->getValue());
    }

    public function testMulticharacterOperators()
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

    public function testStandardConformingStrings()
    {
        $string = " 'foo\\\\bar' e'foo\\\\bar' ";
        $stream = $this->lexer->tokenize($string);
        $this->assertEquals('foo\\\\bar', $stream->next()->getValue());
        $this->assertEquals('foo\\bar', $stream->next()->getValue());

        $this->lexer = new Lexer(array('standard_conforming_strings' => false));
        $stream2 = $this->lexer->tokenize($string);
        $this->assertEquals('foo\\bar', $stream2->next()->getValue());
        $this->assertEquals('foo\\bar', $stream2->next()->getValue());
    }

    public function testDollarQuotedString()
    {
        $stream = $this->lexer->tokenize(<<<QRY
    $\$a string$$
    \$foo$ another $$ string ' \\ \$foo$
QRY
);
        $this->assertEquals('a string', $stream->next()->getValue());
        $this->assertEquals(' another $$ string \' \\ ', $stream->next()->getValue());
    }

    /**
     * @expectedException \sad_spirit\pg_builder\exceptions\SyntaxException
     * @expectedExceptionMessage Unterminated /* comment
     */
    public function testUnterminatedCStyleComment()
    {
        $this->lexer->tokenize('/* foo');
    }

    /**
     * @expectedException \sad_spirit\pg_builder\exceptions\SyntaxException
     * @expectedExceptionMessage Unterminated quoted identifier
     */
    public function testUnterminatedQuotedIdentifier()
    {
        $this->lexer->tokenize('update "foo ');
    }

    /**
     * @expectedException \sad_spirit\pg_builder\exceptions\SyntaxException
     * @expectedExceptionMessage Zero-length quoted identifier
     */
    public function testZeroLengthQuotedIdentifier()
    {
        $this->lexer->tokenize('select "" as foo');
    }

    /**
     * @expectedException \sad_spirit\pg_builder\exceptions\SyntaxException
     * @expectedExceptionMessage Unterminated dollar-quoted string
     */
    public function testUnterminatedDollarQuotedString()
    {
        $this->lexer->tokenize('select $foo$ blah $$ blah');
    }

    /**
     * @expectedException \sad_spirit\pg_builder\exceptions\SyntaxException
     * @expectedExceptionMessage Unterminated string literal
     * @dataProvider getUnterminatedLiterals
     */
    public function testUnterminatedStringLiteral($literal)
    {
        $this->lexer->tokenize($literal);
    }

    public function getUnterminatedLiterals()
    {
        return array(
            array("select 'foo  "),
            array(" 'foo \\' '"), // standards_conforming_string is on by default
            array(" e'foo "),
            array(" e'foo\\'"),
            array(" x'1234")
        );
    }

    /**
     * @expectedException \sad_spirit\pg_builder\exceptions\SyntaxException
     * @expectedExceptionMessage Unexpected '{'
     */
    public function testUnexpectedSymbol()
    {
        $this->lexer->tokenize('select foo{bar}');
    }

    public function testNonAsciiIdentifiers()
    {
        $stream = $this->lexer->tokenize('ИмЯ_бЕз_КаВыЧеК "ИмЯ_в_КаВыЧкАх" $ыыы$строка в долларах$ыыы$ :параметр');

        $stream->expect(Token::TYPE_IDENTIFIER, 'ИмЯ_бЕз_КаВыЧеК');
        $stream->expect(Token::TYPE_IDENTIFIER, 'ИмЯ_в_КаВыЧкАх');
        $stream->expect(Token::TYPE_STRING, 'строка в долларах');
        $stream->expect(Token::TYPE_NAMED_PARAM, 'параметр');
        $this->assertTrue($stream->isEOF());
    }

    public function testDowncaseNonAsciiIdentifiers()
    {
        if (!extension_loaded('mbstring')) {
            $this->markTestSkipped('The test requires mbstring extension');
        }

        mb_internal_encoding('CP1251');
        $this->lexer = new Lexer(array('ascii_only_downcasing' => false));
        $stream = $this->lexer->tokenize(mb_convert_encoding('ИмЯ_бЕз_КаВыЧеК "ИмЯ_в_КаВыЧкАх"', 'CP1251', 'UTF8'));
        $stream->expect(Token::TYPE_IDENTIFIER, mb_convert_encoding('имя_без_кавычек', 'CP1251', 'UTF8'));
        $stream->expect(Token::TYPE_IDENTIFIER, mb_convert_encoding('ИмЯ_в_КаВыЧкАх', 'CP1251', 'UTF8'));
        $this->assertTrue($stream->isEOF());
    }
}