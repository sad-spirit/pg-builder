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

namespace sad_spirit\pg_builder\nodes\range\json;

use sad_spirit\pg_builder\nodes\{
    Identifier,
    ScalarExpression,
    TypeName
};
use sad_spirit\pg_builder\nodes\expressions\StringConstant;
use sad_spirit\pg_builder\nodes\json\{
    JsonFormat,
    JsonQueryBehaviours,
    WrapperAndQuotesProperties
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node for formatted column definitions in json_table() clause
 *
 * @property JsonFormat $format
 */
class JsonFormattedColumnDefinition extends JsonTypedColumnDefinition
{
    use WrapperAndQuotesProperties;
    use JsonQueryBehaviours;

    /** @var JsonFormat */
    protected $p_format;

    /**
     * Constructor
     *
     * @param Identifier $name
     * @param TypeName $type
     * @param JsonFormat $format
     * @param StringConstant|null $path
     * @param string|null $wrapper
     * @param bool|null $keepQuotes
     * @param ScalarExpression|string|null $onEmpty
     * @param ScalarExpression|string|null $onError
     */
    public function __construct(
        Identifier $name,
        TypeName $type,
        JsonFormat $format,
        ?StringConstant $path = null,
        ?string $wrapper = null,
        ?bool $keepQuotes = null,
        $onEmpty = null,
        $onError = null
    ) {
        parent::__construct($name, $type, $path);

        $this->p_format = $format;
        $this->p_format->setParentNode($this);

        $this->setWrapper($wrapper);
        $this->setKeepQuotes($keepQuotes);
        $this->setOnEmpty($onEmpty);
        $this->setOnError($onError);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonFormattedColumnDefinition($this);
    }
}
