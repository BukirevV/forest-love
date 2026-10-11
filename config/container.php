<?php

/**
 * DI-контейнер: сервисы бизнес-логики — синглтоны.
 * Используются через конструкторную инъекцию в контроллерах
 * или Yii::$container->get(Service::class).
 */

use app\components\payment\PaymentGatewayInterface;
use app\components\sms\SmsSenderInterface;
use app\components\storage\StorageInterface;

return [
    'singletons' => [
        SmsSenderInterface::class => static fn() => Yii::$app->get('smsSender'),
        PaymentGatewayInterface::class => static fn() => Yii::$app->get('paymentGateway'),
        StorageInterface::class => static fn() => Yii::$app->get('storage'),
        app\services\SocialNetworkResolver::class => app\services\SocialNetworkResolver::class,
        app\services\ExifService::class => app\services\ExifService::class,
        app\services\ImageService::class => app\services\ImageService::class,
        app\services\NotificationService::class => app\services\NotificationService::class,
        app\services\AuthService::class => app\services\AuthService::class,
        app\services\PlantingService::class => app\services\PlantingService::class,
        app\services\ModerationService::class => app\services\ModerationService::class,
        app\services\NamedTreeService::class => app\services\NamedTreeService::class,
        app\services\PaymentService::class => app\services\PaymentService::class,
        app\services\MapDataService::class => app\services\MapDataService::class,
        app\services\AdStatsService::class => app\services\AdStatsService::class,
        app\services\RateLimitService::class => app\services\RateLimitService::class,
    ],
];
