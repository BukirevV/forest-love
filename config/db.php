<?php

return [
    'dsn' => $_ENV['DB_DSN'],
    'username' => $_ENV['DB_USER'],
    'password' => $_ENV['DB_PASSWORD'],
    'class' => \yii\db\Connection::class,
    'enableSchemaCache' => !YII_DEBUG,
    'schemaCacheDuration' => 3600,
    'schemaCache' => 'cache',
];
