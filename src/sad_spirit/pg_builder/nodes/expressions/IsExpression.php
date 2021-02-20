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
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\{
    exceptions\InvalidArgumentException,
    nodes\ScalarExpression,
    TreeWalker
};

/**
 * AST node representing "foo IS [NOT] keyword" expression
 *
 * Allowed keywords are TRUE / FALSE / NULL / UNKNOWN / DOCUMENT / [NFC|NFD|NFKC|NFKD] NORMALIZED
 *
 * @property ScalarExpression $argument
 * @property string           $what
 */
class IsExpression extends NegatableExpression
{
    public const NULL            = 'null';
    public const TRUE            = 'true';
    public const FALSE           = 'false';
    public const UNKNOWN         = 'unknown';
    public const DOCUMENT        = 'document';
    public const NORMALIZED      = 'normalized';
    public const NFC_NORMALIZED  = 'nfc normalized';
    public const NFD_NORMALIZED  = 'nfd normalized';
    public const NFKC_NORMALIZED = 'nfkc normalized';
    public const NFKD_NORMALIZED = 'nfkd normalized';

    private const ALLOWED_KEYWORDS = [
        self::NULL            => true,
        self::TRUE            => true,
        self::FALSE           => true,
        self::UNKNOWN         => true,
        self::DOCUMENT        => true,
        self::NORMALIZED      => true,
        self::NFC_NORMALIZED  => true,
        self::NFD_NORMALIZED  => true,
        self::NFKC_NORMALIZED => true,
        self::NFKD_NORMALIZED => true
    ];

    /** @var ScalarExpression */
    protected $p_argument;
    /** @var string */
    protected $p_what;

    public function __construct(ScalarExpression $argument, string $what, bool $not = false)
    {
        $this->generatePropertyNames();

        $this->p_argument = $argument;
        $this->p_argument->setParentNode($this);

        $this->p_not = $not;

        $this->setWhat($what);
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function setWhat(string $what): void
    {
        if (!isset(self::ALLOWED_KEYWORDS[$what])) {
            throw new InvalidArgumentException("Unknown keyword '{$what}' for right side of IS expression");
        }
        $this->p_what = $what;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkIsExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_IS;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_NONE;
    }
}
