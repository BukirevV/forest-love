<?php

return [
    'appName' => 'Лес Любви',
    'appUrl' => env('APP_URL', 'http://localhost'),
    'adminEmail' => env('ADMIN_EMAIL', 'admin@example.com'),
    'senderEmail' => env('SENDER_EMAIL', 'noreply@example.com'),
    'senderName' => 'Лес Любви',
    'bsVersion' => '5.x',

    // Карта
    'map.defaultCenter' => [55.75, 37.62],
    'map.defaultZoom' => 5,
    'map.maxPointsPerRequest' => 5000,
    'map.cacheTtl' => 60,
    'map.tileUrl' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    'map.tileAttribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',

    // Загрузка файлов
    'upload.maxFileSize' => 15 * 1024 * 1024,
    'upload.allowedTypes' => ['jpg', 'jpeg', 'png', 'webp', 'heic'],
    'upload.allowedMimeTypes' => ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'],
    'upload.maxPhotosPerPlanting' => 100,
    'image.maxSide' => 1600,
    'image.thumbSide' => 400,
    'image.quality' => 85,

    // Авторизация
    'auth.codeLength' => 6,
    'auth.codeTtl' => 600,
    'auth.codeMaxAttempts' => 5,
    'auth.resendInterval' => 60,
    'auth.maxCodesPerHour' => 5,
    'auth.emailTokenTtl' => 86400,
    'auth.passwordResetTokenTtl' => 3600,
    'auth.passwordMinLength' => 8,
    'auth.rememberMeDuration' => 3600 * 24 * 30,
    'auth.defaultRegion' => 'RU',

    // Ограничение частоты (кол-во запросов, окно в секундах)
    'rateLimit.login' => [10, 600],
    'rateLimit.signup' => [5, 3600],
    'rateLimit.photoUpload' => [30, 3600],

    // Именное размещение
    'named.price' => 0,          // уточняется
    'named.currency' => 'RUB',   // уточняется
    'named.durationDays' => null, // null = бессрочно

    // Ссылки «Кто посадил»
    'links.maxCount' => 50,

    // Таблица доменов соцсетей: домен => код сети
    'social.domains' => [
        'vk.com' => 'vk',
        'vk.ru' => 'vk',
        'm.vk.com' => 'vk',
        't.me' => 'telegram',
        'telegram.me' => 'telegram',
        'telegram.org' => 'telegram',
        'instagram.com' => 'instagram',
        'facebook.com' => 'facebook',
        'fb.com' => 'facebook',
        'youtube.com' => 'youtube',
        'youtu.be' => 'youtube',
        'ok.ru' => 'ok',
        'x.com' => 'x',
        'twitter.com' => 'x',
        'tiktok.com' => 'tiktok',
        'dzen.ru' => 'dzen',
        'rutube.ru' => 'rutube',
        'linkedin.com' => 'linkedin',
        'github.com' => 'github',
        'pinterest.com' => 'pinterest',
        'threads.net' => 'threads',
    ],

    // Тексты SMS (подстановка {code}, {app})
    'sms.templates' => [
        'signup' => 'Код подтверждения {app}: {code}',
        'login' => 'Код для входа в {app}: {code}',
        'password_reset' => 'Код восстановления пароля {app}: {code}',
        'contact_change' => 'Код подтверждения нового номера {app}: {code}',
    ],
];
