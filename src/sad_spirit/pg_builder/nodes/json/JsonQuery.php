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
 * @copyright 2014-2022 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\json;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing the json_query() expression
 *
 * @property string|null                  $wrapper
 * @property bool|null                    $keepQuotes
 * @property string|ScalarExpression|null $onEmpty
 * @property string|ScalarExpression|null $onError
 */
class JsonQuery extends JsonQueryCommon
{
    use ReturningProperty;

    public const WRAPPER_WITHOUT       = 'without';
    public const WRAPPER_CONDITIONAL   = 'with conditional';
    public const WRAPPER_UNCONDITIONAL = 'with unconditional';

    public const ALLOWED_WRAPPERS = [
        self::WRAPPER_WITHOUT       => true,
        self::WRAPPER_CONDITIONAL   => true,
        self::WRAPPER_UNCONDITIONAL => true
    ];

    public const ALLOWED_BEHAVIOURS = [
        self::BEHAVIOUR_ERROR,
        self::BEHAVIOUR_NULL,
        self::BEHAVIOUR_EMPTY_ARRAY,
        self::BEHAVIOUR_EMPTY_OBJECT
    ];

    /** @var string|null */
    protected $p_wrapper;
    /** @var bool|null */
    protected $p_keepQuotes;
    /** @var string|ScalarExpression|null */
    protected $p_onEmpty = null;
    /** @var string|ScalarExpression|null */
    protected $p_onError = null;

    /**
     * Constructor
     *
     * @param JsonFormattedValue $context
     * @param ScalarExpression $path
     * @param JsonArgumentList|null $passing
     * @param JsonReturning|null $returning
     * @param string|null $wrapper
     * @param bool|null $keepQuotes
     * @param string|ScalarExpression|null $onEmpty
     * @param string|ScalarExpression|null $onError
     */
    public function __construct(
        JsonFormattedValue $context,
        ScalarExpression $path,
        ?JsonArgumentList $passing = null,
        ?JsonReturning $returning = null,
        ?string $wrapper = null,
        ?bool $keepQuotes = null,
        $onEmpty = null,
        $onError = null
    ) {
        parent::__construct($context, $path, $passing);
        if (null !== $returning) {
            $this->p_returning = $returning;
            $this->p_returning->setParentNode($this);
        }
        $this->setWrapper($wrapper);
        $this->p_keepQuotes = $keepQuotes;
        $this->setOnEmpty($onEmpty);
        $this->setOnError($onError);
    }

    public function setWrapper(?string $wrapper): void
    {
        if (null !== $wrapper && !isset(self::ALLOWED_WRAPPERS[$wrapper])) {
            throw new InvalidArgumentException(sprintf(
                "Unrecognized value '%s' for WRAPPER clause, expected one of '%s'",
                $wrapper,
                implode("', '", self::ALLOWED_WRAPPERS)
            ));
        }
        $this->p_wrapper = $wrapper;
    }

    public function setKeepQuotes(?bool $keepQuotes): void
    {
        $this->p_keepQuotes = $keepQuotes;
    }

    /**
     * Sets the value for ON EMPTY clause
     *
     * @param string|ScalarExpression|null $onEmpty an instance of ScalarExpression corresponds to "DEFAULT ..." value
     * @return void
     */
    public function setOnEmpty($onEmpty): void
    {
        $this->setBehaviour($this->p_onEmpty, 'ON EMPTY', self::ALLOWED_BEHAVIOURS, $onEmpty);
    }

    /**
     * Sets the value for ON ERROR clause
     *
     * @param string|ScalarExpression|null $onError an instance of ScalarExpression corresponds to "DEFAULT ..." value
     * @return void
     */
    public function setOnError($onError): void
    {
        $this->setBehaviour($this->p_onError, 'ON ERROR', self::ALLOWED_BEHAVIOURS, $onError);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonQuery($this);
    }
}
