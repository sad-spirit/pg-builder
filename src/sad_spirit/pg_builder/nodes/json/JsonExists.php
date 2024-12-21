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
 * AST node representing the json_exists() expression
 */
class JsonExists extends JsonQueryCommon
{
    use ReturningTypenameProperty;
    use JsonExistsBehaviours;

    public function __construct(
        JsonFormattedValue $context,
        ScalarExpression $path,
        ?JsonArgumentList $passing = null,
        ?TypeName $returning = null,
        ?string $onError = null
    ) {
        parent::__construct($context, $path, $passing);

        if (null !== $returning) {
            $this->p_returning = $returning;
            $this->p_returning->setParentNode($this);
        }
        $this->setOnError($onError);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonExists($this);
    }
}
