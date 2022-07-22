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

use sad_spirit\pg_builder\{
    SelectCommon,
    TreeWalker
};
use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    FunctionLike,
    GenericNode,
    ScalarExpression
};
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * AST node representing the json_array() expression
 *
 * @psalm-property JsonFormattedValueList|SelectCommon|null $arguments
 *
 * @property JsonFormattedValueList|JsonFormattedValue[]|SelectCommon|null $arguments
 */
class JsonArray extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;
    use AbsentOnNullProperty {
        setAbsentOnNull as private setAbsentOnNullImpl;
    }
    use ReturningProperty;

    /** @var JsonFormattedValueList|SelectCommon|null */
    protected $p_arguments = null;

    public function __construct(
        ?GenericNode $arguments = null,
        ?bool $absentOnNull = null,
        ?JsonReturning $returning = null
    ) {
        $this->generatePropertyNames();

        if (null !== $arguments) {
            if (!($arguments instanceof JsonFormattedValueList) && !($arguments instanceof SelectCommon)) {
                throw new InvalidArgumentException(sprintf(
                    '%s requires an instance of either SelectCommon or JsonFormattedValueList for $arguments, %s given',
                    __CLASS__,
                    get_class($arguments)
                ));
            }
            $this->p_arguments = $arguments;
            $this->p_arguments->setParentNode($this);
        }

        $this->setAbsentOnNull($absentOnNull);

        if (null !== $returning) {
            $this->p_returning = $returning;
            $this->p_returning->setParentNode($this);
        }
    }

    public function setArguments(?GenericNode $arguments): void
    {
        if (null !== $arguments) {
            if (!($arguments instanceof JsonFormattedValueList) && !($arguments instanceof SelectCommon)) {
                throw new InvalidArgumentException(sprintf(
                    '%s requires an instance of either SelectCommon or JsonFormattedValueList for $arguments, %s given',
                    __CLASS__,
                    get_class($arguments)
                ));
            }
        }
        $this->setProperty($this->p_arguments, $arguments);
        if (!($arguments instanceof JsonFormattedValueList)) {
            $this->setAbsentOnNull(null);
        }
    }

    public function setAbsentOnNull(?bool $absentOnNull): void
    {
        if (null !== $absentOnNull && !($this->p_arguments instanceof JsonFormattedValueList)) {
            throw new InvalidArgumentException(
                'Setting $absentOnNull is only possible when $arguments is an instance of JsonFormattedValueList'
            );
        }
        $this->setAbsentOnNullImpl($absentOnNull);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonArray($this);
    }
}
