<?php

$now = time();
return [
    'pending' => [
        'id' => 1, 'user_id' => 1, 'planting_id' => 1, 'type' => 'named_tree', 'amount' => '500.00', 'currency' => 'RUB',
        'status' => 'pending', 'provider' => 'fake', 'provider_payment_id' => 'fake_pending_1', 'idempotency_key' => 'idem-key-1',
        'meta' => json_encode(['title' => 'Дуб нашей семьи', 'days' => null], JSON_UNESCAPED_UNICODE),
        'created_at' => $now, 'updated_at' => $now,
    ],
];
