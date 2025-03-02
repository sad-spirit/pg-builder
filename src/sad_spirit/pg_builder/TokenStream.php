<?php

/*
 * This file is part of sad_spirit/pg_builder:
 * query builder for Postgres backed by SQL parser
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder;

/**
 * Represents a token stream
 */
class TokenStream implements \Stringable
{
    private int $current;

    /**
     * If stream is at KeywordToken, its keyword property is kept here
     */
    private ?Keyword $keyword = null;

    /**
     * Constructor
     *
     * @param Token[] $tokens Array of tokens extracted by Lexer
     * @param string  $source Source of SQL statement, mostly for exceptions
     */
    public function __construct(private readonly array $tokens, private readonly string $source)
    {
        $this->reset();
    }

    /**
     * Returns a string representation of the token stream
     *
     * @return string
     */
    public function __toString(): string
    {
        return \implode("\n", $this->tokens);
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
        $this->keyword = $this->tokens[$this->current]->getKeyword();

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
        $this->keyword  = $this->tokens[$this->current]->getKeyword();
    }

    /**
     * Checks if end of stream was reached
     *
     * @return bool
     */
    public function isEOF(): bool
    {
        return $this->tokens[$this->current]->getType() === TokenType::EOF;
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
        $this->current = 0;
        $this->keyword = $this->tokens[$this->current]->getKeyword();
    }

    /**
     * Checks whether current token matches given type and/or value
     *
     * Possible parameters
     * * type and value (or array of possible values)
     * * just type ($type is integer, $values is null)
     *
     * @param TokenType            $type
     * @param string|string[]|null $values
     * @return bool
     */
    public function matches(TokenType $type, string|array|null $values = null): bool
    {
        return $this->tokens[$this->current]->matches($type, $values);
    }

    /**
     * Returns Keyword for the current token, if any
     */
    public function getKeyword(): ?Keyword
    {
        return $this->keyword;
    }

    /**
     * Matches the current Keyword against given ones and returns it in case of success
     *
     * @param Keyword ...$keywords
     * @return Keyword|null
     */
    public function matchesAnyKeyword(Keyword ...$keywords): ?Keyword
    {
        if (null === $this->keyword) {
            return null;
        }
        return \in_array($this->keyword, $keywords, true) ? $this->keyword : null;
    }

    /**
     * Checks whether current token matches the given special character
     *
     * @param string|string[] $char
     * @return bool
     */
    public function matchesSpecialChar(string|array $char): bool
    {
        return null === $this->keyword && $this->tokens[$this->current]->matches(TokenType::SPECIAL_CHAR, $char);
    }

    /**
     * Checks whether current token belongs to any type from the given list
     */
    public function matchesAnyType(TokenType ...$types): bool
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
     * @param Keyword|Keyword[] ...$keywords
     * @return bool
     */
    public function matchesKeywordSequence(Keyword|array ...$keywords): bool
    {
        if (null === $this->keyword || $this->current + \count($keywords) >= \count($this->tokens)) {
            return false;
        }
        $index = 0;
        foreach ($keywords as $keyword) {
            if (
                $keyword instanceof Keyword
                ? $keyword !== $this->tokens[$this->current + $index++]->getKeyword()
                : !$this->tokens[$this->current + $index++]->matchesAnyKeyword(...$keyword)
            ) {
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
     * @param TokenType            $type
     * @param string|string[]|null $values
     * @return Token
     * @throws exceptions\SyntaxException
     */
    public function expect(TokenType $type, string|array|null $values = null): Token
    {
        $token = $this->tokens[$this->current];
        if (!$token->matches($type, $values)) {
            throw exceptions\SyntaxException::expectationFailed($type, $values, $token, $this->source);
        }
        $this->next();

        return $token;
    }

    /**
     * Matches the current Keyword against given ones and returns it or throws a syntax error
     *
     * @param Keyword ...$keywords
     * @return Keyword
     */
    public function expectKeyword(Keyword ...$keywords): Keyword
    {
        if (
            null === $this->keyword
            || (
                [$this->keyword] !== $keywords
                && !\in_array($this->keyword, $keywords, true)
            )
        ) {
            throw exceptions\SyntaxException::expectationFailed(
                TokenType::KEYWORD,
                \array_map(fn (Keyword $keyword) => $keyword->value, $keywords),
                $this->tokens[$this->current],
                $this->source
            );
        }

        $keyword = $this->keyword;
        $this->next();
        return $keyword;
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
