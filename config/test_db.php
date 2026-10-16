<?php

$db = require __DIR__ . '/db.php';
$db['dsn'] = $_ENV['DB_TEST_DSN'];
$db['enableSchemaCache'] = false;

return $db;
