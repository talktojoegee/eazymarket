<?php

return [
    'rows_per_tick' => env('BULK_UPLOAD_ROWS_PER_TICK', 300),
    'file_disk' => env('BULK_UPLOAD_DISK', 'local'),
    'temp_dir' => 'bulk-uploads',
    'preview_rows' => env('BULK_UPLOAD_PREVIEW_ROWS', 50),
    'preview_rows_per_request' => env('BULK_UPLOAD_PREVIEW_ROWS_PER_REQUEST', 750),
    'missing_media_entries' => 50,
    'max_file_kb' => env('BULK_UPLOAD_MAX_FILE_KB', 102400),
    'retention_days' => env('BULK_UPLOAD_RETENTION_DAYS', 7),
    'retention_failed_days' => env('BULK_UPLOAD_RETENTION_FAILED_DAYS', 30),
    'orphan_csv_hours' => env('BULK_UPLOAD_ORPHAN_CSV_HOURS', 6),
    'lock_seconds' => env('BULK_UPLOAD_LOCK_SECONDS', 3600),
];
