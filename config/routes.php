<?php

return [
    '' => 'site/index',
    'about' => 'site/about',
    'privacy' => 'site/privacy',
    'terms' => 'site/terms',

    'tree/<id:\d+>' => 'tree/view',
    'ad/<slug:[\w-]+>' => 'ad/view',

    'GET api/map/points' => 'map/points',
    'GET api/map/tree/<id:\d+>' => 'map/tree',
    'GET api/map/ads' => 'map/ad-points',
    'GET api/map/banners' => 'map/banners',
    'POST api/map/ad-click/<id:\d+>' => 'map/ad-click',
    'POST api/map/ad-impression' => 'map/ad-impression',

    'signup' => 'auth/signup',
    'login' => 'auth/login',
    'logout' => 'auth/logout',
    'verify-phone' => 'auth/verify-phone',
    'confirm-email' => 'auth/confirm-email',
    'password-reset' => 'auth/request-password-reset',
    'password-reset/new' => 'auth/reset-password',

    'cabinet' => 'cabinet/index',
    'cabinet/profile' => 'cabinet/profile',
    'cabinet/plantings' => 'planting/index',
    'cabinet/plantings/new' => 'planting/create',
    'cabinet/plantings/exif' => 'planting/exif',
    'cabinet/plantings/<id:\d+>' => 'planting/view',
    'cabinet/plantings/<id:\d+>/edit' => 'planting/update',
    'POST cabinet/plantings/<id:\d+>/submit' => 'planting/submit',
    'POST cabinet/plantings/<id:\d+>/delete' => 'planting/delete',
    'cabinet/plantings/<id:\d+>/named' => 'payment/named',
    'POST cabinet/plantings/<planting_id:\d+>/photos' => 'photo/upload',
    'POST cabinet/photos/<id:\d+>/delete' => 'photo/delete',
    'POST cabinet/photos/<id:\d+>/main' => 'photo/set-main',
    'cabinet/payments' => 'payment/history',

    'payment/success/<id:\d+>' => 'payment/success',
    'payment/fail/<id:\d+>' => 'payment/fail',
    'POST payment/webhook/<provider:\w+>' => 'payment/webhook',

    'admin' => 'admin/default/index',
    'admin/<controller:[\w-]+>' => 'admin/<controller>/index',
    'admin/<controller:[\w-]+>/<action:[\w-]+>' => 'admin/<controller>/<action>',
];
