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

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    FunctionLike,
    GenericNode,
    ScalarExpression
};
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * AST node representing NORMALIZE(...) function call with special arguments format
 *
 * Previously this was parsed to a FunctionExpression node having pg_catalog.normalize as function name.
 * As Postgres itself now outputs the original SQL standard form of the expression when generating SQL,
 * we follow the suit by creating a separate Node with SQL standard output.
 *
 * @property      ScalarExpression $argument
 * @property-read string|null      $form
 */
class NormalizeExpression extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    public const NFC  = 'nfc';
    public const NFD  = 'nfd';
    public const NFKC = 'nfkc';
    public const NFKD = 'nfkd';

    public const FORMS = [
        self::NFC,
        self::NFD,
        self::NFKC,
        self::NFKD
    ];

    /** @var ScalarExpression */
    protected $p_argument;
    /** @var string|null */
    protected $p_form;

    public function __construct(ScalarExpression $argument, string $form = null)
    {
        if (null !== $form && !in_array($form, self::FORMS, true)) {
            throw new InvalidArgumentException("Unknown normalization form '$form' in NormalizeExpression");
        }

        $this->generatePropertyNames();

        $this->p_argument = $argument;
        $this->p_argument->setParentNode($this);

        $this->p_form = $form;
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkNormalizeExpression($this);
    }
}
