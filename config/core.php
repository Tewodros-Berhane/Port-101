<?php

return [
    'audit_logs' => [
        'retention_days' => (int) env('AUDIT_LOG_RETENTION_DAYS', 365),
    ],
    'attachments' => [
        'disk' => env('ATTACHMENTS_DISK', env('FILESYSTEM_DISK', 'local')),
        'max_size_kb' => (int) env('ATTACHMENTS_MAX_SIZE_KB', 10240),
    ],
    'notifications' => [
        'governance' => [
            'min_severity' => env('NOTIFICATIONS_MIN_SEVERITY', 'low'),
            'escalation_enabled' => filter_var(
                env('NOTIFICATIONS_ESCALATION_ENABLED', false),
                FILTER_VALIDATE_BOOLEAN
            ),
            'escalation_severity' => env('NOTIFICATIONS_ESCALATION_SEVERITY', 'high'),
            'escalation_delay_minutes' => (int) env('NOTIFICATIONS_ESCALATION_DELAY_MINUTES', 30),
            'digest_enabled' => filter_var(
                env('NOTIFICATIONS_DIGEST_ENABLED', true),
                FILTER_VALIDATE_BOOLEAN
            ),
            'digest_frequency' => env('NOTIFICATIONS_DIGEST_FREQUENCY', 'daily'),
            'digest_day_of_week' => (int) env('NOTIFICATIONS_DIGEST_DAY_OF_WEEK', 1),
            'digest_time' => env('NOTIFICATIONS_DIGEST_TIME', '08:00'),
            'digest_timezone' => env('NOTIFICATIONS_DIGEST_TIMEZONE', 'UTC'),
        ],
    ],
];
