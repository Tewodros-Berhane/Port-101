<?php

return [
    'audit_logs' => [
        'retention_days' => (int) env('AUDIT_LOG_RETENTION_DAYS', 365),
    ],
    'attachments' => [
        'disk' => env('ATTACHMENTS_DISK', env('FILESYSTEM_DISK', 'local')),
        'max_size_kb' => (int) env('ATTACHMENTS_MAX_SIZE_KB', 10240),
    ],
];

