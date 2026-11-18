<?php

return [
    'images_zip' => [
        'max_entries' => env('IMAGES_ZIP_MAX_ENTRIES', 500),
        'max_total_bytes' => env('IMAGES_ZIP_MAX_TOTAL_BYTES', 64 * 1024 * 1024),
    ],
];
