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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;

/**
 * AST node for locking options in SELECT clause
 *
 * @property-read string $strength
 * @property-read bool   $noWait
 * @property-read bool   $skipLocked
 * @extends NonAssociativeList<QualifiedName, iterable<QualifiedName>, QualifiedName>
 */
class LockingElement extends NonAssociativeList
{
    use NonRecursiveNode;
    use HasBothPropsAndOffsets;

    public const UPDATE        = 'update';
    public const NO_KEY_UPDATE = 'no key update';
    public const SHARE         = 'share';
    public const KEY_SHARE     = 'key share';

    private const ALLOWED_STRENGTHS = [
        self::UPDATE        => true,
        self::NO_KEY_UPDATE => true,
        self::SHARE         => true,
        self::KEY_SHARE     => true
    ];

    /** @var string */
    protected $p_strength = self::UPDATE;
    /** @var bool */
    protected $p_noWait = false;
    /** @var bool */
    protected $p_skipLocked = false;

    protected static function getAllowedElementClasses(): array
    {
        return [QualifiedName::class];
    }

    /**
     * Constructor for LockingElement
     *
     * @param string               $strength
     * @param array<QualifiedName> $relations
     * @param bool                 $noWait
     * @param bool                 $skipLocked
     */
    public function __construct(string $strength, array $relations = [], bool $noWait = false, bool $skipLocked = false)
    {
        if (!isset(self::ALLOWED_STRENGTHS[$strength])) {
            throw new InvalidArgumentException("Unknown locking strength '{$strength}'");
        }
        if ($noWait && $skipLocked) {
            throw new InvalidArgumentException("Only one of NOWAIT or SKIP LOCKED is allowed in locking clause");
        }

        $this->generatePropertyNames();
        $this->p_strength   = $strength;
        $this->p_noWait     = $noWait;
        $this->p_skipLocked = $skipLocked;
        parent::__construct($relations);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkLockingElement($this);
    }
}
