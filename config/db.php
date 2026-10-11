<?php

return [
//    'class' => 'yii\db\Connection',
    'dsn' => env('DB_DSN', 'pgsql:host=127.0.0.1;dbname=forestdb'),
    'username' => env('DB_USER', 'forestuser'),
    'password' => env('DB_PASSWORD', 'forestP@ssw0rd'),
    'class' => \yii\db\Connection::class,
//    'dsn' => 'mysql:host=localhost;dbname=forest',
//    'username' => 'forestuser',
//    'password' => 'ForestP@ssw0rd',
//    'charset' => 'utf8',

    'enableSchemaCache' => !YII_DEBUG,
    'schemaCacheDuration' => 3600,
    'schemaCache' => 'cache',
];
