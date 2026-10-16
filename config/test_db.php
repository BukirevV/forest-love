<?php

$db = require __DIR__ . '/db.php';
$db['dsn'] = $_ENV['DB_TEST_DSN', 'pgsql:host=127.0.0.1;dbname=forest_test');
$db['enableSchemaCache'] = false;

return $db;
