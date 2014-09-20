<?php

spl_autoload_register(function($class) {
    if ('\\' == $class[0]) {
        $class = substr($class, 1);
    }

    if (0 === strpos($class, 'sad_spirit\\pg_builder\\tests\\')
        && file_exists($file = __DIR__. str_replace('\\', '/', substr($class, strlen('sad_spirit\\pg_builder\\tests'))) . '.php')
    ) {
        require_once $file;
    }
});

require_once __DIR__ . '/../src/sad_spirit/pg_builder/autoloader.php';
require_once __DIR__ . '/../../pg-wrapper/src/sad_spirit/pg_wrapper/autoloader.php';

date_default_timezone_set('UTC');

if (is_readable(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    require_once __DIR__ . '/config.php.dist';
}