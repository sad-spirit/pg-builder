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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing the window frame (opt_frame_clause production in grammar)
 *
 * @property-read string                $type
 * @property-read WindowFrameBound      $start
 * @property-read WindowFrameBound|null $end
 * @property-read string|null           $exclusion
 */
class WindowFrameClause extends GenericNode
{
    public const RANGE       = 'range';
    public const ROWS        = 'rows';
    public const GROUPS      = 'groups';

    public const CURRENT_ROW = 'current row';
    public const GROUP       = 'group';
    public const TIES        = 'ties';

    private const ALLOWED_TYPES = [
        self::RANGE  => true,
        self::ROWS   => true,
        self::GROUPS => true
    ];

    private const ALLOWED_EXCLUSIONS = [
        self::CURRENT_ROW => true,
        self::GROUP       => true,
        self::TIES        => true
    ];

    /** @var string */
    protected $p_type;
    /** @var WindowFrameBound */
    protected $p_start;
    /** @var WindowFrameBound|null */
    protected $p_end = null;
    /** @var string|null */
    protected $p_exclusion;

    public function __construct(
        string $type,
        WindowFrameBound $start,
        WindowFrameBound $end = null,
        ?string $exclusion = null
    ) {
        $this->generatePropertyNames();

        if (!isset(self::ALLOWED_TYPES[$type])) {
            throw new InvalidArgumentException("Unknown window frame type '{$type}'");
        }
        $this->p_type = $type;

        if (null !== $exclusion && !isset(self::ALLOWED_EXCLUSIONS[$exclusion])) {
            throw new InvalidArgumentException("Unknown window frame exclusion '{$exclusion}'");
        }
        $this->p_exclusion = $exclusion;

        // like in frame_extent production in gram.y, reject invalid frame cases
        if (WindowFrameBound::FOLLOWING === $start->direction && !$start->value) {
            throw new InvalidArgumentException('Frame start cannot be UNBOUNDED FOLLOWING');
        }
        if (null === $end) {
            if (WindowFrameBound::FOLLOWING === $start->direction && $start->value) {
                throw new InvalidArgumentException('Frame starting from following row cannot end with current row');
            }

        } else {
            if (WindowFrameBound::PRECEDING === $end->direction && !$end->value) {
                throw new InvalidArgumentException("Frame end cannot be UNBOUNDED PRECEDING");
            }
            if (
                WindowFrameBound::CURRENT_ROW === $start->direction
                && WindowFrameBound::PRECEDING === $end->direction
            ) {
                throw new InvalidArgumentException("Frame starting from current row cannot have preceding rows");
            }
            if (
                WindowFrameBound::FOLLOWING === $start->direction
                && in_array($end->direction, [WindowFrameBound::CURRENT_ROW, WindowFrameBound::PRECEDING])
            ) {
                throw new InvalidArgumentException("Frame starting from following row cannot have preceding rows");
            }
        }

        $this->p_start = $start;
        $this->p_start->setParentNode($this);

        if (null !== $end) {
            $this->p_end = $end;
            $this->p_end->setParentNode($this);
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkWindowFrameClause($this);
    }
}
