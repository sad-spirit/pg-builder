<?php

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('UTC');

if (is_readable(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    require_once __DIR__ . '/config.php.dist';
}
