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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\json;

use sad_spirit\pg_builder\{
    exceptions\InvalidArgumentException,
    nodes\GenericNode,
    nodes\NonRecursiveNode,
    TreeWalker
};

/**
 * Represents the FORMAT clause in various JSON expressions
 *
 * Looks like it is 100% noise right now, as 'json' format is hardcoded in grammar
 * and no encodings other than utf-8 work
 *
 * @property-read string      $format   This can be only 'json' currently, see json_format_clause_opt definition
 * @property-read string|null $encoding
 */
class JsonFormat extends GenericNode
{
    use NonRecursiveNode;

    public const FORMAT_JSON = 'json';

    private const ALLOWED_FORMATS = [
        self::FORMAT_JSON => true
    ];

    public const ENCODING_UTF8  = 'utf8';
    public const ENCODING_UTF16 = 'utf16';
    public const ENCODING_UTF32 = 'utf32';

    private const ALLOWED_ENCODINGS = [
        self::ENCODING_UTF8  => true,
        self::ENCODING_UTF16 => true,
        self::ENCODING_UTF32 => true
    ];

    /** @var string */
    protected $p_format;
    /** @var ?string */
    protected $p_encoding;

    public function __construct(string $format = self::FORMAT_JSON, ?string $encoding = null)
    {
        if (!isset(self::ALLOWED_FORMATS[$format])) {
            throw new InvalidArgumentException("Unrecognized JSON format '$format'");
        }

        if (null !== $encoding) {
            $lower = strtolower($encoding);
            if (!isset(self::ALLOWED_ENCODINGS[$lower])) {
                throw new InvalidArgumentException("Unrecognized JSON encoding '$encoding'");
            }
        }

        $this->generatePropertyNames();
        $this->p_format   = $format;
        $this->p_encoding = $lower ?? null;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonFormat($this);
    }
}
