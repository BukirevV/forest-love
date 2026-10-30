<?php

require_once __DIR__ . '/bootstrap.php';

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';
$common = require __DIR__ . '/common.php';

$config = [
    'id' => 'forest-love-console',
    'basePath' => dirname(__DIR__),
    'language' => 'ru-RU',
    'timeZone' => 'Europe/Moscow',
    'bootstrap' => ['log', 'queue'],
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
        '@tests' => '@app/tests',
        '@webroot' => '@app/web',
        '@web' => $_ENV['APP_URL'],
        '@storage' => '@app/storage',
        '@uploads' => '@web/uploads',
    ],
    'container' => require __DIR__ . '/container.php',
    'components' => array_merge($common, [
        'db' => $db,
        'urlManager' => [
            'class' => yii\web\UrlManager::class,
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'baseUrl' => $_ENV['APP_URL'],
            'hostInfo' => $_ENV['APP_URL'],
            'rules' => require __DIR__ . '/routes.php',
        ],
    ]),
    'params' => $params,
    'controllerMap' => [
        'migrate' => [
            'class' => yii\console\controllers\MigrateController::class,
            'migrationPath' => [
                '@app/migrations',
                '@yii/rbac/migrations',
            ],
            'migrationNamespaces' => [
                'yii\queue\db\migrations',
            ],
        ],
    ],
];

if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

return $config;
