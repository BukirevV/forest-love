<?php

$now = time();
$base = ['user_id' => 1, 'source' => 'upload', 'width' => 640, 'height' => 480, 'file_size' => 10000, 'mime_type' => 'image/jpeg', 'created_at' => $now, 'updated_at' => $now];

return [
    'published' => $base + ['id' => 1, 'planting_id' => 1, 'file_path' => 'plantings/test/1.jpg', 'public_path' => 'plantings/test/1p.jpg', 'thumb_path' => 'plantings/test/1t.jpg', 'taken_at' => '2026-05-01', 'status' => 'approved'],
    'draft' => $base + ['id' => 2, 'planting_id' => 2, 'file_path' => 'plantings/test/2.jpg', 'taken_at' => '2026-05-02', 'status' => 'pending'],
    'pending' => $base + ['id' => 3, 'planting_id' => 3, 'file_path' => 'plantings/test/3.jpg', 'taken_at' => '2026-05-03', 'status' => 'pending'],
    'foreign' => ['user_id' => 2] + $base + ['id' => 4, 'planting_id' => 4, 'file_path' => 'plantings/test/4.jpg', 'public_path' => 'plantings/test/4p.jpg', 'taken_at' => '2026-05-04', 'status' => 'approved'],
];
