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

namespace sad_spirit\pg_builder\nodes\json;

use sad_spirit\pg_builder\nodes\{
    ScalarExpression,
    TypeName
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing the json_value() expression
 */
class JsonValue extends JsonQueryCommon
{
    use ReturningTypenameProperty;
    use JsonValueBehaviours;

    /**
     * Constructor
     *
     * @param JsonFormattedValue $context
     * @param ScalarExpression $path
     * @param JsonArgumentList|null $passing
     * @param TypeName|null $returning
     * @param string|ScalarExpression|null $onEmpty
     * @param string|ScalarExpression|null $onError
     */
    public function __construct(
        JsonFormattedValue $context,
        ScalarExpression $path,
        ?JsonArgumentList $passing = null,
        ?TypeName $returning = null,
        $onEmpty = null,
        $onError = null
    ) {
        parent::__construct($context, $path, $passing);
        if (null !== $returning) {
            $this->p_returning = $returning;
            $this->p_returning->setParentNode($this);
        }
        $this->setOnEmpty($onEmpty);
        $this->setOnError($onError);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonValue($this);
    }
}
