<?php

/**
 * Компоненты, общие для web и console.
 */

$smsDriver = $_ENV['SMS_DRIVER'];
$paymentDriver = $_ENV['PAYMENT_DRIVER'];

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
        'useFileTransport' => (bool)$_ENV['MAILER_USE_FILE_TRANSPORT'],
        'transport' => [
            'dsn' => $_ENV['MAILER_DSN'],
        ],
    ],
    'smsSender' => $smsDriver === 'http'
        ? [
            'class' => app\components\sms\HttpSmsSender::class,
            'apiUrl' => $_ENV['SMS_API_URL'],
            'apiKey' => $_ENV['SMS_API_KEY'],
            'senderName' => $_ENV['SMS_SENDER_NAME'],
        ]
        : [
            'class' => app\components\sms\FileSmsSender::class,
        ],
    'paymentGateway' => $paymentDriver === 'http'
        ? [
            'class' => app\components\payment\HttpPaymentGateway::class,
            'apiUrl' => $_ENV['PAYMENT_API_URL'],
            'shopId' => $_ENV['PAYMENT_SHOP_ID'],
            'secretKey' => $_ENV['PAYMENT_SECRET_KEY'],
            'webhookSecret' => $_ENV['PAYMENT_WEBHOOK_SECRET'],
            'allowedIps' => array_filter(explode(',', (string)$_ENV['PAYMENT_WEBHOOK_IPS'])),
        ]
        : [
            'class' => app\components\payment\FakePaymentGateway::class,
            'webhookSecret' => $_ENV['PAYMENT_WEBHOOK_SECRET'],
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
