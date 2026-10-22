<?php

require_once __DIR__ . '/bootstrap.php';

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';
$common = require __DIR__ . '/common.php';

$config = [
    'id' => 'forest-love',
    'name' => 'Лес Любви',
    'basePath' => dirname(__DIR__),
    'language' => 'ru-RU',
    'sourceLanguage' => 'ru-RU',
    'timeZone' => 'Europe/Moscow',
    'bootstrap' => ['log', 'queue'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
    'container' => require __DIR__ . '/container.php',
    'modules' => [
        'admin' => [
            'class' => app\modules\admin\Module::class,
        ],
    ],
    'components' => array_merge($common, [
        'request' => [
            'cookieValidationKey' => $_ENV['COOKIE_VALIDATION_KEY'],
            'csrfCookie' => ['httpOnly' => true, 'secure' => !YII_DEBUG, 'sameSite' => 'Lax'],
            'parsers' => [
                'application/json' => yii\web\JsonParser::class,
            ],
        ],
        'session' => [
            'cookieParams' => ['httponly' => true, 'secure' => !YII_DEBUG, 'samesite' => 'Lax'],
        ],
        'user' => [
            'identityClass' => app\models\User::class,
            'enableAutoLogin' => true,
            'loginUrl' => ['auth/login'],
            'identityCookie' => ['name' => '_identity', 'httpOnly' => true, 'secure' => !YII_DEBUG],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => require __DIR__ . '/routes.php',
        ],
        'assetManager' => [
            'appendTimestamp' => true,
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            'viewPath' => '@app/mail', // или '@app/mail' для Basic
            'useFileTransport' => false, // false - реальная отправка, true - сохранение в runtime/mail
            'transport' => [
                'class' => 'Swift_SmtpTransport',
                'host' => 'smtp.yandex.ru', // SMTP-сервер Яндекса
                'username' => $_ENV['ADMIN_EMAIL'], // Ваш полный адрес почты
                'password' => $_ENV['ADMIN_EMAIL_PASSWORD'], // Пароль, созданный в Шаге 1!
                'port' => 465, // Порт для SSL
                'encryption' => 'ssl', // Тип шифрования
                // Опционально: отключение проверки SSL-сертификата (см. примечание ниже)
                'streamOptions' => [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ],
            ],
        ],
    ]),
    'params' => $params,
];

if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        'allowedIPs' => ['127.0.0.1', '::1'],
    ];
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
