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
 * @copyright 2014-2018 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\nodes\lists\TypeModifierList,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException;


/**
 * Represents a type name for INTERVAL type
 *
 * A separate class is required as interval may have a mask specified, like in
 * <code>
 * interval '2 days 2 seconds' minute to second
 * </code>
 * While this mask can be converted to an integer type modifier and casting can be
 * done to underlying type pg_catalog.interval, this is too implementation-specific.
 * So we keep the mask in text form and use the standard INTERVAL type name.
 *
 * @property string $mask
 */
class IntervalTypeName extends TypeName
{
    protected static $allowedMasks = array(
        'year'             => true,
        'month'            => true,
        'day'              => true,
        'hour'             => true,
        'minute'           => true,
        'second'           => true,
        'year to month'    => true,
        'day to hour'      => true,
        'day to minute'    => true,
        'day to second'    => true,
        'hour to minute'   => true,
        'hour to second'   => true,
        'minute to second' => true
    );

    public function __construct(TypeModifierList $typeModifiers = null)
    {
        $this->props['setOf']     = false;
        $this->props['bounds']    = array();
        $this->props['name']      = null;
        $this->props['mask']      = '';
        $this->setNamedProperty('modifiers', $typeModifiers ?: new TypeModifierList());
    }

    public function setMask($mask = '')
    {
        $mask = (string)$mask;
        if (strlen($mask) && !isset(self::$allowedMasks[$mask])) {
            throw new InvalidArgumentException("Unknown mask '{$mask}' for interval type");
        }
        $this->props['mask'] = $mask;
    }
}
