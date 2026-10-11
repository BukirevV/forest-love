<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => env('DB_DSN', 'pgsql:host=127.0.0.1;dbname=forest'),
    'username' => env('DB_USER', 'forest'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'enableSchemaCache' => !YII_DEBUG,
    'schemaCacheDuration' => 3600,
    'schemaCache' => 'cache',
];
