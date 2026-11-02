<?php

return [
    // One explicit driver, shared by HTTP validation and Horizon. No fallback.
    'driver' => 'gd',
    'quality' => (int) env('MEDIA_WEBP_QUALITY', 84),
    'max_bytes' => 10 * 1024 * 1024,
    'max_pixels' => 12_000_000,
    'max_dimension' => 12_000,
    'stale_after_seconds' => 300,
    'variants' => ['full' => 1000, 'card' => 250, 'thumb' => 100],
];
