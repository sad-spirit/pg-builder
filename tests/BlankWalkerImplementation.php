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

namespace sad_spirit\pg_builder\tests;

use sad_spirit\pg_builder\BlankWalker;
use sad_spirit\pg_builder\nodes\Identifier;

/**
 * A concrete subclass of BlankWalker that saves all visited Identifiers
 */
class BlankWalkerImplementation extends BlankWalker
{
    public const IDENTIFIER_MASK = '[sdivuwxfgejm]\d+';

    /** @var array<string, true> */
    public array $identifiers = [];

    public function walkIdentifier(Identifier $node): null
    {
        if (\preg_match('{^' . self::IDENTIFIER_MASK . '$}', $node->value)) {
            if (isset($this->identifiers[$node->value])) {
                throw new \Exception("Duplicate identifier {$node->value}");
            }
            $this->identifiers[$node->value] = true;
        }
        return null;
    }
}
