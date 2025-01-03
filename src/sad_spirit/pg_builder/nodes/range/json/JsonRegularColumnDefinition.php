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
use sad_spirit\pg_builder\enums\JsonWrapper;
use sad_spirit\pg_builder\nodes\expressions\StringConstant;
use sad_spirit\pg_builder\nodes\json\{
    JsonFormat,
    JsonValueBehaviours,
    WrapperAndQuotesProperties
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node for regular column definitions in json_table() expression
 *
 * @property-read JsonFormat|null $format
 */
class JsonRegularColumnDefinition extends JsonTypedColumnDefinition
{
    use WrapperAndQuotesProperties;
    use JsonValueBehaviours;

    protected ?JsonFormat $p_format = null;

    /**
     * Constructor
     *
     * @param Identifier $name
     * @param TypeName $type
     * @param JsonFormat|null $format
     * @param StringConstant|null $path
     * @param JsonWrapper|null $wrapper
     * @param bool|null $keepQuotes
     * @param ScalarExpression|string|null $onEmpty
     * @param ScalarExpression|string|null $onError
     */
    public function __construct(
        Identifier $name,
        TypeName $type,
        ?JsonFormat $format = null,
        ?StringConstant $path = null,
        ?JsonWrapper $wrapper = null,
        ?bool $keepQuotes = null,
        $onEmpty = null,
        $onError = null
    ) {
        parent::__construct($name, $type, $path);
        $this->setWrapper($wrapper);
        $this->setKeepQuotes($keepQuotes);
        $this->setOnEmpty($onEmpty);
        $this->setOnError($onError);

        if (null !== $format) {
            $this->p_format = $format;
            $this->p_format->setParentNode($this);
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonRegularColumnDefinition($this);
    }
}
