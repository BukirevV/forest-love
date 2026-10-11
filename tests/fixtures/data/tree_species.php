<?php

$now = time();
return [
    'oak' => ['id' => 1, 'name' => 'Дуб черешчатый', 'latin_name' => 'Quercus robur', 'sort' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
    'birch' => ['id' => 2, 'name' => 'Берёза повислая', 'latin_name' => 'Betula pendula', 'sort' => 20, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
    'old' => ['id' => 3, 'name' => 'Неактивная порода', 'sort' => 30, 'is_active' => false, 'created_at' => $now, 'updated_at' => $now],
];
