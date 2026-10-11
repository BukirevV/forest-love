<?php

$now = time();
return [
    'vk' => ['id' => 1, 'planting_id' => 1, 'url' => 'https://vk.com/forest', 'label' => 'Команда', 'network' => 'vk', 'sort' => 0, 'created_at' => $now, 'updated_at' => $now],
    'tg' => ['id' => 2, 'planting_id' => 1, 'url' => 'https://t.me/forest', 'label' => null, 'network' => 'telegram', 'sort' => 1, 'created_at' => $now, 'updated_at' => $now],
];
