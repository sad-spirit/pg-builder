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

/**
 * Adds $wrapper and $keepQuotes properties
 *
 * Those map to "[WITH ... | WITHOUT] WRAPPER" and "[KEEP | OMIT] QUOTES" clauses in json_query() and column
 * definitions. These clauses always appear together and are mutually exclusive.
 *
 * @property string|null $wrapper
 * @property bool|null   $keepQuotes
 */
trait WrapperAndQuotesProperties
{
    /** @var string|null */
    protected $p_wrapper;
    /** @var bool|null */
    protected $p_keepQuotes;

    public function setWrapper(?string $wrapper): void
    {
        if (null !== $wrapper) {
            if (!in_array($wrapper, JsonKeywords::WRAPPERS)) {
                throw new InvalidArgumentException(sprintf(
                    "Unrecognized value '%s' for WRAPPER clause, expected one of '%s'",
                    $wrapper,
                    implode("', '", JsonKeywords::WRAPPERS)
                ));
            }
            if (JsonKeywords::WRAPPER_WITHOUT !== $wrapper && null !== $this->p_keepQuotes) {
                throw new InvalidArgumentException("WITH WRAPPER behaviour must not be specified when QUOTES is used");
            }
        }
        $this->p_wrapper = $wrapper;
    }

    public function setKeepQuotes(?bool $keepQuotes): void
    {
        if (null !== $keepQuotes && null !== $this->p_wrapper && JsonKeywords::WRAPPER_WITHOUT !== $this->p_wrapper) {
            throw new InvalidArgumentException("QUOTES behaviour must not be specified when WITH WRAPPER is used");
        }
        $this->p_keepQuotes = $keepQuotes;
    }
}
