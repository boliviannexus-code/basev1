<?php

return [
    'photos' => [
        'disk' => env('PLAYER_PHOTOS_DISK', 'public'),
        'directory' => env('PLAYER_PHOTOS_DIRECTORY', 'players/photos'),
        'size' => 600,
        'quality' => (int) env('PLAYER_PHOTOS_WEBP_QUALITY', 78),
        'min_target_kb' => 40,
        'max_target_kb' => 120,
        'future_variants' => [
            'thumbnail' => [
                'size' => 160,
                'quality' => 72,
            ],
        ],
    ],
];
