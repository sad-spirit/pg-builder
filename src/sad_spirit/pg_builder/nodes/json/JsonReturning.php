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
    protected TypeName $p_type;
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

    public function setType(TypeName $type): void
    {
        $this->setRequiredProperty($this->p_type, $type);
    }

    public function setFormat(?JsonFormat $format): void
    {
        $this->setProperty($this->p_format, $format);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonReturning($this);
    }
}
