<?php

$now = time();
$hash = Yii::$app->security->generatePasswordHash('password123', 4);

return [
    'owner' => [
        'id' => 1, 'email' => 'owner@example.com', 'phone' => null, 'password_hash' => $hash,
        'auth_key' => 'owner-auth-key-000000000000000001', 'display_name' => 'Владелец',
        'status' => 'active', 'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now,
    ],
    'other' => [
        'id' => 2, 'email' => null, 'phone' => '+79990000002', 'password_hash' => $hash,
        'auth_key' => 'other-auth-key-000000000000000002', 'display_name' => 'Другой',
        'status' => 'active', 'phone_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now,
    ],
    'moderator' => [
        'id' => 3, 'email' => 'moderator@example.com', 'password_hash' => $hash,
        'auth_key' => 'moder-auth-key-000000000000000003', 'display_name' => 'Модератор',
        'status' => 'active', 'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now,
    ],
    'admin' => [
        'id' => 4, 'email' => 'admin@example.com', 'password_hash' => $hash,
        'auth_key' => 'admin-auth-key-000000000000000004', 'display_name' => 'Админ',
        'status' => 'active', 'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now,
    ],
    'unverified' => [
        'id' => 5, 'email' => 'new@example.com', 'password_hash' => $hash,
        'auth_key' => 'unver-auth-key-000000000000000005',
        'status' => 'unverified', 'created_at' => $now, 'updated_at' => $now,
    ],
];
