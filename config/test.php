<?php

require_once __DIR__ . '/bootstrap.php';

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/test_db.php';
$common = require __DIR__ . '/common.php';

$common['smsSender'] = ['class' => app\components\sms\FileSmsSender::class];
$common['paymentGateway'] = [
    'class' => app\components\payment\FakePaymentGateway::class,
    'webhookSecret' => 'test-secret',
];
$common['queue'] = ['class' => yii\queue\sync\Queue::class, 'handle' => true];
$common['mailer']['useFileTransport'] = true;
$common['storage']['privatePath'] = '@runtime/test-storage/private';
$common['storage']['publicPath'] = '@runtime/test-storage/public';
$common['cache'] = ['class' => yii\caching\ArrayCache::class];

return [
    'id' => 'forest-love-tests',
    'basePath' => dirname(__DIR__),
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
        '@tests' => '@app/tests',
        '@webroot' => '@app/web',
        '@web' => '/',
    ],
    'language' => 'ru-RU',
    'container' => require __DIR__ . '/container.php',
    'modules' => [
        'admin' => ['class' => app\modules\admin\Module::class],
    ],
    'components' => array_merge($common, [
        'db' => $db,
        'urlManager' => [
            'showScriptName' => true,
            'enablePrettyUrl' => true,
            'rules' => require __DIR__ . '/routes.php',
        ],
        'user' => [
            'identityClass' => app\models\User::class,
        ],
        'request' => [
            'cookieValidationKey' => 'test',
            'enableCsrfValidation' => false,
        ],
    ]),
    'params' => $params,
];
