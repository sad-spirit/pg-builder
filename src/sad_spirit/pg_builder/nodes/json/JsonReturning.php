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

use sad_spirit\pg_builder\{
    nodes\GenericNode,
    nodes\TypeName,
    TreeWalker
};

/**
 * Represents the RETURNING clause in various JSON expressions
 *
 * @property TypeName        $type
 * @property JsonFormat|null $format
 */
class JsonReturning extends GenericNode
{
    /** @var TypeName */
    protected $p_type;
    /** @var JsonFormat|null */
    protected $p_format;

    public function __construct(TypeName $type, ?JsonFormat $format = null)
    {
        $this->generatePropertyNames();

        $this->p_type = $type;
        $this->p_type->setParentNode($this);

        if (null !== $format) {
            $this->p_format = $format;
            $this->p_format->setParentNode($this);
        }
    }

    public function setType(TypeName $type): void
    {
        $this->setRequiredProperty($this->p_type, $type);
    }

    public function setFormat(?JsonFormat $format): void
    {
        $this->setProperty($this->p_format, $format);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonReturning($this);
    }
}
