<?php

namespace App\Core\Platform;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PerformanceAuditService
{
    /**
     * @var array<int, array{table: string, label: string, expected_indexes: array<int, string>}>
     */
    private const HOTSPOT_TABLES = [
        [
            'table' => 'jobs',
            'label' => 'Queue jobs',
            'expected_indexes' => [
                'jobs_queue_reserved_available_idx',
                'jobs_queue_available_idx',
            ],
        ],
        [
            'table' => 'failed_jobs',
            'label' => 'Failed jobs',
            'expected_indexes' => [
                'failed_jobs_queue_failed_at_idx',
                'failed_jobs_failed_at_idx',
            ],
        ],
        [
            'table' => 'notifications',
            'label' => 'In-app notifications',
            'expected_indexes' => [
                'notifications_notifiable_read_created_idx',
            ],
        ],
        [
            'table' => 'audit_logs',
            'label' => 'Audit logs',
            'expected_indexes' => [
                'audit_logs_company_action_created_idx',
                'audit_logs_company_user_created_idx',
            ],
        ],
        [
            'table' => 'report_exports',
            'label' => 'Report exports',
            'expected_indexes' => [
                'report_exports_status_failed_at_idx',
                'report_exports_company_status_failed_at_idx',
            ],
        ],
        [
            'table' => 'integration_events',
            'label' => 'Integration events',
            'expected_indexes' => [
                'integration_events_company_published_occurred_idx',
            ],
        ],
        [
            'table' => 'webhook_endpoints',
            'label' => 'Webhook endpoints',
            'expected_indexes' => [
                'webhook_endpoints_company_active_delivery_idx',
            ],
        ],
        [
            'table' => 'webhook_deliveries',
            'label' => 'Webhook deliveries',
            'expected_indexes' => [
                'webhook_deliveries_status_dead_lettered_idx',
                'webhook_deliveries_company_status_dead_lettered_idx',
                'webhook_deliveries_company_endpoint_status_idx',
                'webhook_deliveries_company_status_retry_idx',
            ],
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function configSummary(string $driver): array
    {
        $connection = (string) config('database.default');

        return [
            'database_connection' => $connection,
            'database_name' => (string) config("database.connections.{$connection}.database"),
            'database_driver' => $driver,
            'driver_supports_pg_stats' => $driver === 'pgsql',
            'audit_output_dir' => (string) config('core.performance.audit_output_dir'),
            'load_test_output_dir' => (string) config('core.performance.load_test_output_dir'),
            'k6_script' => (string) config('core.performance.k6_script'),
            'hot_path_tables' => collect(self::HOTSPOT_TABLES)
                ->pluck('table')
                ->all(),
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     driver: string,
     *     generated_at: string,
     *     tables: array<int, array<string, mixed>>,
     *     recommendations: array<int, string>,
     *     config: array<string, mixed>
     * }
     */
    public function run(): array
    {
        $driver = DB::connection()->getDriverName();
        $indexMap = $this->indexMap($driver);
        $statsMap = $this->tableStatsMap($driver);

        $tables = collect(self::HOTSPOT_TABLES)
            ->map(function (array $definition) use ($indexMap, $statsMap) {
                $tableName = $definition['table'];
                $indexes = $indexMap[$tableName] ?? [];
                $stats = $statsMap[$tableName] ?? [];
                $exists = Schema::hasTable($tableName);
                $missingIndexes = $exists
                    ? array_values(array_diff($definition['expected_indexes'], $indexes))
                    : $definition['expected_indexes'];

                return [
                    'table' => $tableName,
                    'label' => $definition['label'],
                    'exists' => $exists,
                    'estimated_rows' => (int) ($stats['estimated_rows'] ?? 0),
                    'seq_scan' => (int) ($stats['seq_scan'] ?? 0),
                    'idx_scan' => (int) ($stats['idx_scan'] ?? 0),
                    'total_bytes' => (int) ($stats['total_bytes'] ?? 0),
                    'total_mb' => round(((int) ($stats['total_bytes'] ?? 0)) / 1024 / 1024, 2),
                    'expected_indexes' => $definition['expected_indexes'],
                    'present_indexes' => $indexes,
                    'missing_expected_indexes' => $missingIndexes,
                ];
            })
            ->values();

        $recommendations = $this->recommendations($tables->all(), $driver);

        return [
            'ok' => $tables->every(fn (array $table) => $table['exists'] && $table['missing_expected_indexes'] === []),
            'driver' => $driver,
            'generated_at' => now()->toIso8601String(),
            'tables' => $tables->all(),
            'recommendations' => $recommendations,
            'config' => $this->configSummary($driver),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function indexMap(string $driver): array
    {
        if ($driver !== 'pgsql') {
            return [];
        }

        return collect(DB::select(
            <<<'SQL'
                select
                    tablename,
                    indexname
                from pg_indexes
                where schemaname = current_schema()
            SQL
        ))
            ->groupBy(fn ($row) => (string) $row->tablename)
            ->map(fn ($rows) => collect($rows)->pluck('indexname')->map(fn ($name) => (string) $name)->all())
            ->all();
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function tableStatsMap(string $driver): array
    {
        if ($driver !== 'pgsql') {
            return [];
        }

        return collect(DB::select(
            <<<'SQL'
                select
                    relname as table_name,
                    coalesce(n_live_tup, 0) as estimated_rows,
                    coalesce(seq_scan, 0) as seq_scan,
                    coalesce(idx_scan, 0) as idx_scan,
                    pg_total_relation_size(relid) as total_bytes
                from pg_stat_user_tables
                where schemaname = current_schema()
            SQL
        ))
            ->mapWithKeys(fn ($row) => [
                (string) $row->table_name => [
                    'estimated_rows' => (int) $row->estimated_rows,
                    'seq_scan' => (int) $row->seq_scan,
                    'idx_scan' => (int) $row->idx_scan,
                    'total_bytes' => (int) $row->total_bytes,
                ],
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $tables
     * @return array<int, string>
     */
    private function recommendations(array $tables, string $driver): array
    {
        $recommendations = [];

        if ($driver !== 'pgsql') {
            $recommendations[] = 'Performance audit is optimized for PostgreSQL statistics; current driver uses a reduced audit surface.';
        }

        foreach ($tables as $table) {
            if (! $table['exists']) {
                $recommendations[] = "{$table['label']} table is missing from the schema.";

                continue;
            }

            if ($table['missing_expected_indexes'] !== []) {
                $recommendations[] = sprintf(
                    '%s is missing expected indexes: %s',
                    $table['label'],
                    implode(', ', $table['missing_expected_indexes']),
                );
            }

            if (
                $table['estimated_rows'] >= 1000
                && $table['seq_scan'] > $table['idx_scan']
            ) {
                $recommendations[] = sprintf(
                    '%s is showing more sequential scans than index scans; review query plans under production load.',
                    $table['label'],
                );
            }
        }

        if ($recommendations === []) {
            $recommendations[] = 'Hot-path tables have the expected index baseline. Continue with representative load testing.';
        }

        return $recommendations;
    }
}
