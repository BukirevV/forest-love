<?php

/**
 * Компоненты, общие для web и console.
 */

$smsDriver = env('SMS_DRIVER', 'file');
$paymentDriver = env('PAYMENT_DRIVER', 'fake');

return [
    'cache' => [
        'class' => yii\caching\FileCache::class,
        // Для продакшена: 'class' => yii\redis\Cache::class,
    ],
    'authManager' => [
        'class' => yii\rbac\DbManager::class,
        'cache' => 'cache',
    ],
    'formatter' => [
        'class' => yii\i18n\Formatter::class,
        'locale' => 'ru-RU',
        'defaultTimeZone' => 'Europe/Moscow',
        'timeZone' => 'Europe/Moscow',
        'dateFormat' => 'php:d.m.Y',
        'datetimeFormat' => 'php:d.m.Y H:i',
        'currencyCode' => 'RUB',
        'nullDisplay' => '—',
    ],
    'i18n' => [
        'translations' => [
            'app*' => [
                'class' => yii\i18n\PhpMessageSource::class,
                'basePath' => '@app/messages',
                'sourceLanguage' => 'ru-RU',
            ],
        ],
    ],
    'mailer' => [
        'class' => yii\symfonymailer\Mailer::class,
        'viewPath' => '@app/mail',
        'useFileTransport' => (bool)env('MAILER_USE_FILE_TRANSPORT', true),
        'transport' => [
            'dsn' => env('MAILER_DSN', 'null://null'),
        ],
    ],
    'smsSender' => $smsDriver === 'http'
        ? [
            'class' => app\components\sms\HttpSmsSender::class,
            'apiUrl' => env('SMS_API_URL'),
            'apiKey' => env('SMS_API_KEY'),
            'senderName' => env('SMS_SENDER_NAME', 'LesLubvi'),
        ]
        : [
            'class' => app\components\sms\FileSmsSender::class,
        ],
    'paymentGateway' => $paymentDriver === 'http'
        ? [
            'class' => app\components\payment\HttpPaymentGateway::class,
            'apiUrl' => env('PAYMENT_API_URL'),
            'shopId' => env('PAYMENT_SHOP_ID'),
            'secretKey' => env('PAYMENT_SECRET_KEY'),
            'webhookSecret' => env('PAYMENT_WEBHOOK_SECRET'),
            'allowedIps' => array_filter(explode(',', (string)env('PAYMENT_WEBHOOK_IPS', ''))),
        ]
        : [
            'class' => app\components\payment\FakePaymentGateway::class,
            'webhookSecret' => env('PAYMENT_WEBHOOK_SECRET', 'fake-secret'),
        ],
    'storage' => [
        'class' => app\components\storage\LocalStorage::class,
        'privatePath' => '@storage',
        'publicPath' => '@webroot/uploads',
        'publicUrl' => '@web/uploads',
    ],
    'queue' => [
        'class' => yii\queue\db\Queue::class,
        'db' => 'db',
        'tableName' => '{{%queue}}',
        'channel' => 'default',
        'mutex' => yii\mutex\PgsqlMutex::class, // для MySQL: yii\mutex\MysqlMutex
        'ttr' => 300,
        'attempts' => 3,
    ],
    'log' => [
        'traceLevel' => YII_DEBUG ? 3 : 0,
        'targets' => [
            [
                'class' => yii\log\FileTarget::class,
                'levels' => ['error', 'warning'],
            ],
            [
                'class' => yii\log\FileTarget::class,
                'categories' => ['payment*'],
                'logFile' => '@runtime/logs/payment.log',
                'logVars' => [],
            ],
            [
                'class' => yii\log\FileTarget::class,
                'categories' => ['moderation*'],
                'logFile' => '@runtime/logs/moderation.log',
                'logVars' => [],
            ],
            [
                'class' => yii\log\FileTarget::class,
                'categories' => ['sms*'],
                'logFile' => '@runtime/logs/sms.log',
                'logVars' => [],
                'levels' => ['info', 'error', 'warning'],
            ],
        ],
    ],
];
