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
 * Represents a token stream
 */
class TokenStream
{
    /**
     * @var Token[]
     */
    private $tokens;
    private $current;
    private $source;

    /**
     * A flag to prevent useless calls to Token::matches() from matchesKeyword()
     * @var bool
     */
    private $isAtKeyword = false;

    /**
     * Constructor
     *
     * @param array  $tokens Array of tokens extracted by Lexer
     * @param string $source Source of SQL statement, mostly for exceptions
     * @throws exceptions\SyntaxException
     */
    public function __construct(array $tokens, string $source)
    {
        $this->tokens = $tokens;
        $this->source = $source;
        $this->reset();
    }

    /**
     * Returns a string representation of the token stream
     *
     * @return string
     */
    public function __toString()
    {
        return implode("\n", $this->tokens);
    }

    /**
     * Sets the pointer to the next token and returns the old one
     *
     * @return Token
     * @throws exceptions\SyntaxException if no more tokens are available
     */
    public function next(): Token
    {
        if (!isset($this->tokens[++$this->current])) {
            throw new exceptions\SyntaxException('Unexpected end of input');
        }
        $this->isAtKeyword = $this->tokens[$this->current]->matches(Token::TYPE_KEYWORD);

        return $this->tokens[$this->current - 1];
    }

    /**
     * Moves the pointer to given number of positions after the current
     *
     * @param int $number
     * @throws exceptions\SyntaxException
     * @return void
     */
    public function skip(int $number): void
    {
        if (!isset($this->tokens[$this->current + $number])) {
            throw new exceptions\SyntaxException('Unexpected end of input');
        }

        $this->current += $number;
        $this->isAtKeyword = $this->tokens[$this->current]->matches(Token::TYPE_KEYWORD);
    }

    /**
     * Checks if end of stream was reached
     *
     * @return bool
     */
    public function isEOF(): bool
    {
        return $this->tokens[$this->current]->getType() === Token::TYPE_EOF;
    }

    /**
     * Returns the current token
     *
     * @return Token
     */
    public function getCurrent(): Token
    {
        return $this->tokens[$this->current];
    }

    /**
     * Looks at the next token
     *
     * @param int $number Look that many positions after the current token
     * @return Token
     * @throws exceptions\SyntaxException
     */
    public function look(int $number = 1): Token
    {
        if (!isset($this->tokens[$this->current + $number])) {
            throw new exceptions\SyntaxException('Unexpected end of input');
        }

        return $this->tokens[$this->current + $number];
    }

    /**
     * Resets the pointer to the first token in the stream
     */
    public function reset(): void
    {
        $this->current     = 0;
        $this->isAtKeyword = $this->tokens[$this->current]->matches(Token::TYPE_KEYWORD);
    }

    /**
     * Checks whether current token matches given type and/or value
     *
     * Possible parameters
     * * type and value (or array of possible values)
     * * just type ($type is integer, $values is null)
     *
     * @param int                  $type
     * @param string|string[]|null $values
     * @return bool
     */
    public function matches(int $type, $values = null): bool
    {
        return $this->tokens[$this->current]->matches($type, $values);
    }

    /**
     * Checks whether current token matches the given keyword
     *
     * @param string|string[] $keyword
     * @return bool
     */
    public function matchesKeyword($keyword): bool
    {
        return $this->isAtKeyword && $this->tokens[$this->current]->matches(Token::TYPE_KEYWORD, $keyword);
    }

    /**
     * Checks whether current token matches the given special character
     *
     * @param string|string[] $char
     * @return bool
     */
    public function matchesSpecialChar($char): bool
    {
        return $this->tokens[$this->current]->matches(Token::TYPE_SPECIAL_CHAR, $char);
    }

    /**
     * Checks whether current token belongs to any type from the given list
     *
     * @param int ...$types
     * @return bool
     */
    public function matchesAnyType(int ...$types): bool
    {
        foreach ($types as $type) {
            if ($this->tokens[$this->current]->matches($type)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks whether tokens starting from current match the given sequence of keywords
     *
     * @param mixed ...$keywords
     * @return bool
     */
    public function matchesKeywordSequence(...$keywords)
    {
        if (!$this->isAtKeyword || $this->current + count($keywords) >= count($this->tokens)) {
            return false;
        }
        foreach ($keywords as $i => $keyword) {
            if (!$this->tokens[$this->current + $i]->matches(Token::TYPE_KEYWORD, $keyword)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Checks the current token and returns it or throws a syntax error
     *
     * Parameters have the same meaning as those for matches()
     *
     * @param int                  $type
     * @param string|string[]|null $values
     * @return Token
     * @throws exceptions\SyntaxException
     */
    public function expect(int $type, $values = null): Token
    {
        $token = $this->tokens[$this->current];
        if (!$token->matches($type, $values)) {
            throw exceptions\SyntaxException::expectationFailed($type, $values, $token, $this->source);
        }
        $this->next();

        return $token;
    }

    /**
     * Returns the source of tokenized SQL statement
     *
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }
}
