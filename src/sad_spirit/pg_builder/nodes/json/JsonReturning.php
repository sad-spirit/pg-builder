<?php

/*
 * This file is part of sad_spirit/pg_builder:
 * query builder for Postgres backed by SQL parser
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
    /** @internal Maps to `$type` magic property, use the latter instead */
    protected TypeName $p_type;
    /** @internal Maps to `$format` magic property, use the latter instead */
    protected ?JsonFormat $p_format = null;

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

    /** @internal Support method for `$type` magic property, use the property instead */
    public function setType(TypeName $type): void
    {
        $this->setRequiredProperty($this->p_type, $type);
    }

    /** @internal Support method for `$format` magic property, use the property instead */
    public function setFormat(?JsonFormat $format): void
    {
        $this->setProperty($this->p_format, $format);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonReturning($this);
    }
}
