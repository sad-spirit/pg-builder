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

namespace sad_spirit\pg_builder\exceptions;

use sad_spirit\pg_builder\Exception;

/**
 * Namespaced version of SPL's BadMethodCallException
 */
class BadMethodCallException extends \BadMethodCallException implements Exception
{
}
