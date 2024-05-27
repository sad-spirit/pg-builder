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

namespace sad_spirit\pg_builder\tests;

use sad_spirit\pg_builder\{
    Lexer,
    Parser,
    converters\BuilderSupportDecorator
};
use sad_spirit\pg_wrapper\converters\DefaultTypeConverterFactory;

/**
 * This intentionally reuses test for ParserAwareTypeConverterFactory as BuilderSupportDecorator is a replacement
 */
class BuilderSupportDecoratorTest extends ParserAwareTypeConverterFactoryTest
{
    protected function setUp(): void
    {
        $this->factory = new BuilderSupportDecorator(
            new DefaultTypeConverterFactory(),
            new Parser(new Lexer())
        );
    }
}
