<?php

$now = time();
return [
    'spring' => [
        'id' => 1, 'advertiser_id' => 1, 'title' => 'Весенняя посадка', 'slug' => 'vesennyaya-posadka',
        'marketing_text' => '<p>Сажаем деревья вместе</p>', 'legal_text' => 'Реклама. erid: test',
        'status' => 'active', 'starts_at' => $now - 86400, 'ends_at' => $now + 86400 * 30, 'funded_trees_target' => 10,
        'created_at' => $now, 'updated_at' => $now,
    ],
];
