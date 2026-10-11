<?php

$now = time();
$base = ['user_id' => 1, 'species_id' => 1, 'coords_source' => 'manual', 'named_status' => 'none', 'created_at' => $now, 'updated_at' => $now];

return [
    'published' => $base + ['id' => 1, 'latitude' => 55.7500000, 'longitude' => 37.6200000, 'planted_at' => '2026-05-01', 'status' => 'approved', 'approved_at' => $now, 'main_photo_id' => null, 'ad_campaign_id' => 1],
    'draft' => $base + ['id' => 2, 'latitude' => 55.7600000, 'longitude' => 37.6300000, 'planted_at' => '2026-05-02', 'status' => 'draft'],
    'pending' => $base + ['id' => 3, 'latitude' => 55.7700000, 'longitude' => 37.6400000, 'planted_at' => '2026-05-03', 'status' => 'pending'],
    'foreign' => ['user_id' => 2, 'species_id' => 2] + $base + ['id' => 4, 'latitude' => 59.9300000, 'longitude' => 30.3100000, 'planted_at' => '2026-05-04', 'status' => 'approved', 'approved_at' => $now, 'ad_campaign_id' => 1],
];
