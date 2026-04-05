<?php

return [
    'audit_logs' => [
        'retention_days' => (int) env('AUDIT_LOG_RETENTION_DAYS', 365),
    ],
    'backup' => [
        'database_dump_dir' => env(
            'BACKUP_DATABASE_DUMP_DIR',
            storage_path('app/backups/database')
        ),
        'storage_archive_dir' => env(
            'BACKUP_STORAGE_ARCHIVE_DIR',
            storage_path('app/backups/storage')
        ),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
        'attachments_disk' => env(
            'BACKUP_ATTACHMENTS_DISK',
            env('ATTACHMENTS_DISK', env('FILESYSTEM_DISK', 'local'))
        ),
        'local_storage_paths' => [
            'storage/app/private',
            'storage/app/public',
        ],
        'signoff_output_dir' => env(
            'BACKUP_SIGNOFF_OUTPUT_DIR',
            storage_path('app/backup-signoffs')
        ),
    ],
    'recovery' => [
        'restore_drill_root' => env(
            'RESTORE_DRILL_ROOT',
            storage_path('app/restore-drills')
        ),
        'signoff_output_dir' => env(
            'RECOVERY_SIGNOFF_OUTPUT_DIR',
            storage_path('app/recovery-signoffs')
        ),
    ],
    'deployment' => [
        'smoke_check_routes' => [
            'login',
            'company.dashboard',
            'company.settings.show',
            'company.modules.sales',
            'company.modules.inventory',
            'company.modules.projects',
            'company.modules.accounting',
            'company.modules.integrations',
            'platform.dashboard',
            'platform.governance',
            'platform.queue-health',
        ],
    ],
    'performance' => [
        'audit_output_dir' => env(
            'PERFORMANCE_AUDIT_OUTPUT_DIR',
            storage_path('app/performance-audits')
        ),
        'load_test_output_dir' => env(
            'LOAD_TEST_OUTPUT_DIR',
            storage_path('app/load-tests')
        ),
        'load_signoff_output_dir' => env(
            'LOAD_SIGNOFF_OUTPUT_DIR',
            storage_path('app/load-signoffs')
        ),
        'k6_script' => base_path('scripts/ops/k6-api-smoke.js'),
        'load_validation_profiles' => [
            'default' => [
                'max_failed_rate' => (float) env('LOAD_TEST_MAX_FAILED_RATE', 0.02),
                'max_p95_ms' => (float) env('LOAD_TEST_MAX_P95_MS', 1500),
                'endpoint_success_rates' => [
                    'health_success' => (float) env('LOAD_TEST_HEALTH_SUCCESS_RATE', 0.99),
                    'projects_success' => (float) env('LOAD_TEST_PROJECTS_SUCCESS_RATE', 0.95),
                    'inventory_stock_balances_success' => (float) env('LOAD_TEST_INVENTORY_SUCCESS_RATE', 0.95),
                    'sales_orders_success' => (float) env('LOAD_TEST_SALES_SUCCESS_RATE', 0.95),
                    'webhook_endpoints_success' => (float) env('LOAD_TEST_WEBHOOKS_SUCCESS_RATE', 0.95),
                ],
            ],
            'rehearsal' => [
                'max_failed_rate' => (float) env('LOAD_TEST_REHEARSAL_MAX_FAILED_RATE', 0.05),
                'max_p95_ms' => (float) env('LOAD_TEST_REHEARSAL_MAX_P95_MS', 3500),
                'endpoint_success_rates' => [
                    'health_success' => (float) env('LOAD_TEST_REHEARSAL_HEALTH_SUCCESS_RATE', 0.99),
                    'projects_success' => (float) env('LOAD_TEST_REHEARSAL_PROJECTS_SUCCESS_RATE', 0.95),
                    'inventory_stock_balances_success' => (float) env('LOAD_TEST_REHEARSAL_INVENTORY_SUCCESS_RATE', 0.95),
                    'sales_orders_success' => (float) env('LOAD_TEST_REHEARSAL_SALES_SUCCESS_RATE', 0.95),
                    'webhook_endpoints_success' => (float) env('LOAD_TEST_REHEARSAL_WEBHOOKS_SUCCESS_RATE', 0.95),
                ],
            ],
        ],
    ],
    'api' => [
        'rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 120),
    ],
    'integration' => [
        'smoke_check_company_slug' => env('INTEGRATION_SMOKE_CHECK_COMPANY_SLUG', 'demo-company-workflow'),
    ],
    'webhooks' => [
        'require_https' => env('WEBHOOK_REQUIRE_HTTPS'),
        'allow_private_targets' => env('WEBHOOK_ALLOW_PRIVATE_TARGETS'),
        'blocked_hostnames' => [
            'localhost',
            '127.0.0.1',
            '::1',
            'host.docker.internal',
        ],
        'blocked_host_suffixes' => [
            '.localhost',
            '.local',
            '.internal',
            '.test',
        ],
    ],
    'security' => [
        'signoff_output_dir' => env(
            'SECURITY_SIGNOFF_OUTPUT_DIR',
            storage_path('app/security-signoffs')
        ),
    ],
    'queue_failures' => [
        'poison_similarity_window_hours' => (int) env('QUEUE_POISON_SIMILARITY_WINDOW_HOURS', 24),
        'poison_similarity_threshold' => (int) env('QUEUE_POISON_SIMILARITY_THRESHOLD', 3),
        'non_retryable_exceptions' => [
            \Illuminate\Auth\Access\AuthorizationException::class,
            \Illuminate\Database\Eloquent\ModelNotFoundException::class,
            \Illuminate\Validation\ValidationException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            \Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class,
        ],
    ],
    'attachments' => [
        'disk' => env('ATTACHMENTS_DISK', env('FILESYSTEM_DISK', 'local')),
        'max_size_kb' => (int) env('ATTACHMENTS_MAX_SIZE_KB', 10240),
        'download_requires_clean_scan' => filter_var(
            env('ATTACHMENTS_DOWNLOAD_REQUIRES_CLEAN_SCAN', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        'scan' => [
            'enabled' => filter_var(
                env('ATTACHMENTS_SCAN_ENABLED', true),
                FILTER_VALIDATE_BOOLEAN
            ),
            'driver' => env(
                'ATTACHMENTS_SCAN_DRIVER',
                in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)
                    ? 'basic'
                    : 'clamav_binary'
            ),
            'allow_basic_driver_in_non_local' => filter_var(
                env('ATTACHMENTS_SCAN_ALLOW_BASIC_NON_LOCAL', false),
                FILTER_VALIDATE_BOOLEAN
            ),
            'binary' => env('ATTACHMENTS_SCAN_BINARY', 'clamscan'),
            'timeout_seconds' => (int) env('ATTACHMENTS_SCAN_TIMEOUT_SECONDS', 30),
        ],
        'allowlists' => [
            'default' => [
                'mime_types' => [
                    'application/pdf',
                    'text/plain',
                    'text/csv',
                    'text/tab-separated-values',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'image/png',
                    'image/jpeg',
                    'image/webp',
                ],
                'extensions' => [
                    'pdf',
                    'txt',
                    'csv',
                    'tsv',
                    'xls',
                    'xlsx',
                    'docx',
                    'png',
                    'jpg',
                    'jpeg',
                    'webp',
                ],
            ],
            'partner' => [],
            'contact' => [],
            'address' => [],
            'product' => [],
            'project' => [],
            'hr_reimbursement_receipt' => [
                'mime_types' => [
                    'application/pdf',
                    'image/png',
                    'image/jpeg',
                    'image/webp',
                ],
                'extensions' => [
                    'pdf',
                    'png',
                    'jpg',
                    'jpeg',
                    'webp',
                ],
            ],
            'manual_journal' => [
                'mime_types' => [
                    'application/pdf',
                    'text/plain',
                    'text/csv',
                    'text/tab-separated-values',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ],
                'extensions' => [
                    'pdf',
                    'txt',
                    'csv',
                    'tsv',
                    'xls',
                    'xlsx',
                    'docx',
                ],
            ],
            'tax' => [
                'mime_types' => [
                    'application/pdf',
                    'text/plain',
                    'text/csv',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ],
                'extensions' => [
                    'pdf',
                    'txt',
                    'csv',
                    'xls',
                    'xlsx',
                ],
            ],
            'currency' => [
                'mime_types' => [
                    'application/pdf',
                    'text/plain',
                    'text/csv',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ],
                'extensions' => [
                    'pdf',
                    'txt',
                    'csv',
                    'xls',
                    'xlsx',
                ],
            ],
            'uom' => [
                'mime_types' => [
                    'application/pdf',
                    'text/plain',
                    'text/csv',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ],
                'extensions' => [
                    'pdf',
                    'txt',
                    'csv',
                    'xls',
                    'xlsx',
                ],
            ],
            'price_list' => [
                'mime_types' => [
                    'application/pdf',
                    'text/plain',
                    'text/csv',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ],
                'extensions' => [
                    'pdf',
                    'txt',
                    'csv',
                    'xls',
                    'xlsx',
                ],
            ],
        ],
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
            'noisy_event_threshold' => (int) env('NOTIFICATIONS_NOISY_EVENT_THRESHOLD', 3),
        ],
    ],
    'platform_alerting' => [
        'enabled' => filter_var(
            env('PLATFORM_ALERTING_ENABLED', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        'cooldown_minutes' => (int) env('PLATFORM_ALERTING_COOLDOWN_MINUTES', 30),
        'failed_jobs_threshold' => (int) env('PLATFORM_ALERTING_FAILED_JOBS_THRESHOLD', 5),
        'queue_backlog_threshold' => (int) env('PLATFORM_ALERTING_QUEUE_BACKLOG_THRESHOLD', 50),
        'dead_webhook_threshold' => (int) env('PLATFORM_ALERTING_DEAD_WEBHOOK_THRESHOLD', 5),
        'failed_report_export_threshold' => (int) env('PLATFORM_ALERTING_FAILED_REPORT_EXPORT_THRESHOLD', 3),
        'scheduler_drift_minutes' => (int) env('PLATFORM_ALERTING_SCHEDULER_DRIFT_MINUTES', 10),
    ],
];
