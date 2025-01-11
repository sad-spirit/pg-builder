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

use sad_spirit\pg_builder\enums\JsonWrapper;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Adds $wrapper and $keepQuotes properties
 *
 * Those map to "[WITH ... | WITHOUT] WRAPPER" and "[KEEP | OMIT] QUOTES" clauses in json_query() and column
 * definitions. These clauses always appear together and are mutually exclusive.
 *
 * @property JsonWrapper|null $wrapper
 * @property bool|null        $keepQuotes
 */
trait WrapperAndQuotesProperties
{
    protected ?JsonWrapper $p_wrapper = null;
    protected ?bool $p_keepQuotes = null;

    public function setWrapper(?JsonWrapper $wrapper): void
    {
        if (null !== $wrapper && JsonWrapper::WITHOUT !== $wrapper && null !== $this->p_keepQuotes) {
            throw new InvalidArgumentException("WITH WRAPPER behaviour must not be specified when QUOTES is used");
        }
        $this->p_wrapper = $wrapper;
    }

    public function setKeepQuotes(?bool $keepQuotes): void
    {
        if (null !== $keepQuotes && null !== $this->p_wrapper && JsonWrapper::WITHOUT !== $this->p_wrapper) {
            throw new InvalidArgumentException("QUOTES behaviour must not be specified when WITH WRAPPER is used");
        }
        $this->p_keepQuotes = $keepQuotes;
    }
}
