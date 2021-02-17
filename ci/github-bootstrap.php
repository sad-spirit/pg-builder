<?php

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('UTC');

define(
    'TESTS_SAD_SPIRIT_PG_BUILDER_CONNECTION_STRING',
    'host=127.0.0.1 user=postgres password=postgres dbname=pgbuilder_test'
);

define(
    'TESTS_SAD_SPIRIT_PG_BUILDER_PDO_DSN',
    'pgsql:host=127.0.0.1;user=postgres;password=postgres;dbname=pgbuilder_test'
);
