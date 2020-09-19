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
 * Represents a token stream
 */
class TokenStream
{
    /**
     * @var Token[]
     */
    protected $tokens;
    protected $current;
    protected $source;

    /**
     * Constructor
     *
     * @param array  $tokens Array of tokens extracted by Lexer
     * @param string $source Source of SQL statement, mostly for exceptions
     * @throws exceptions\SyntaxException
     */
    public function __construct(array $tokens, $source)
    {
        $this->tokens  = $tokens;
        $this->source  = $source;
        $this->current = 0;
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
    public function next()
    {
        if (!isset($this->tokens[++$this->current])) {
            throw new exceptions\SyntaxException('Unexpected end of input');
        }

        return $this->tokens[$this->current - 1];
    }

    /**
     * Moves the pointer to given number of positions after the current
     *
     * @param int $number
     * @throws exceptions\SyntaxException
     * @return void
     */
    public function skip($number)
    {
        if (!isset($this->tokens[$this->current + $number])) {
            throw new exceptions\SyntaxException('Unexpected end of input');
        }

        $this->current += $number;
    }

    /**
     * Checks if end of stream was reached
     *
     * @return bool
     */
    public function isEOF()
    {
        return $this->tokens[$this->current]->getType() === Token::TYPE_EOF;
    }

    /**
     * Returns the current token
     *
     * @return Token
     */
    public function getCurrent()
    {
        return $this->tokens[$this->current];
    }

    /**
     * Looks at the next token
     *
     * @param integer $number Look that many positions after the current token
     * @return Token
     * @throws exceptions\SyntaxException
     */
    public function look($number = 1)
    {
        if (!isset($this->tokens[$this->current + $number])) {
            throw new exceptions\SyntaxException('Unexpected end of input');
        }

        return $this->tokens[$this->current + $number];
    }

    /**
     * Checks whether current token matches given type and/or value
     *
     * Possible parameters
     * * type and value (or array of possible values)
     * * just type ($type is integer, $values is null)
     * * just value ($type is not integer, $values is null) - token will be tested
     *   with TYPE_KEYWORD and TYPE_SPECIAL
     *
     * @param array|string|integer $type
     * @param array|string|null    $values
     * @return bool
     */
    public function matches($type, $values = null)
    {
        return $this->tokens[$this->current]->matches($type, $values);
    }

    /**
     * Checks whether tokens starting from current match the given sequence of values
     *
     * @param array $sequence
     * @return bool
     */
    public function matchesSequence(array $sequence)
    {
        for ($i = 0; $i < count($sequence); $i++) {
            if (!isset($this->tokens[$this->current + $i])
                || !$this->tokens[$this->current + $i]->matches($sequence[$i])
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
     * @param array|string|integer $type
     * @param array|string|null    $values
     * @return Token
     * @throws exceptions\SyntaxException
     */
    public function expect($type, $values = null)
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
    public function getSource()
    {
        return $this->source;
    }
}
