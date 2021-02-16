<?php

/**
 * Query builder for PostgreSQL backed by a query parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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
    public const IDENTIFIER_MASK = '[sdivuwxfge]\d+';

    /** @var array<string, true> */
    public $identifiers = [];

    public function walkIdentifier(Identifier $node): void
    {
        if (preg_match('{^' . self::IDENTIFIER_MASK . '$}', $node->value)) {
            if (isset($this->identifiers[$node->value])) {
                throw new \Exception("Duplicate identifier {$node->value}");
            }
            $this->identifiers[$node->value] = true;
        }
    }
}
