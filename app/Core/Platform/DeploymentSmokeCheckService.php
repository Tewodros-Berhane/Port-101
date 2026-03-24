<?php

namespace App\Core\Platform;

use App\Core\Company\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Throwable;

class DeploymentSmokeCheckService
{
    public function __construct(
        private readonly PlatformOperationalAlertingService $operationalAlertingService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function configSummary(bool $requireHeartbeat = false): array
    {
        $connection = (string) config('database.default');

        return [
            'app_env' => (string) config('app.env'),
            'app_url' => (string) config('app.url'),
            'database_connection' => $connection,
            'database_name' => (string) config("database.connections.{$connection}.database"),
            'queue_connection' => (string) config('queue.default'),
            'jobs_table' => (string) config('queue.connections.database.table', 'jobs'),
            'failed_jobs_table' => (string) config('queue.failed.table', 'failed_jobs'),
            'require_heartbeat' => $requireHeartbeat,
            'critical_routes' => (array) config('core.deployment.smoke_check_routes', []),
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     checks: array<int, array<string, mixed>>,
     *     config: array<string, mixed>
     * }
     */
    public function run(bool $requireHeartbeat = false): array
    {
        $checks = [
            $this->appKeyCheck(),
            $this->databaseConnectionCheck(),
            $this->pendingMigrationsCheck(),
            $this->criticalRoutesCheck(),
            $this->queueInfrastructureCheck(),
            $this->superAdminPresenceCheck(),
            $this->activeCompanyPresenceCheck(),
            $this->schedulerHeartbeatCheck($requireHeartbeat),
        ];

        return [
            'ok' => collect($checks)->every(fn (array $check) => (bool) ($check['ok'] ?? false)),
            'checks' => $checks,
            'config' => $this->configSummary($requireHeartbeat),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function appKeyCheck(): array
    {
        $key = (string) config('app.key', '');
        $configured = trim($key) !== '';

        return [
            'key' => 'app_key',
            'label' => 'App key configured',
            'ok' => $configured,
            'detail' => $configured
                ? 'Application encryption key is configured.'
                : 'APP_KEY is empty.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseConnectionCheck(): array
    {
        try {
            DB::select('select 1 as ok');

            return [
                'key' => 'database_connection',
                'label' => 'Database connection',
                'ok' => true,
                'detail' => 'Database connection responded successfully.',
            ];
        } catch (Throwable $exception) {
            return [
                'key' => 'database_connection',
                'label' => 'Database connection',
                'ok' => false,
                'detail' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingMigrationsCheck(): array
    {
        $migrationFiles = collect(File::files(database_path('migrations')))
            ->map(fn ($file) => pathinfo($file->getFilename(), PATHINFO_FILENAME))
            ->sort()
            ->values();

        $ran = collect(DB::table('migrations')->pluck('migration'))
            ->sort()
            ->values();

        $pending = $migrationFiles->diff($ran)->values();

        return [
            'key' => 'pending_migrations',
            'label' => 'Pending migrations',
            'ok' => $pending->isEmpty(),
            'detail' => $pending->isEmpty()
                ? 'No pending migrations detected.'
                : 'Pending migrations: '.$pending->implode(', '),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function criticalRoutesCheck(): array
    {
        $routes = collect((array) config('core.deployment.smoke_check_routes', []))
            ->filter(fn ($route) => is_string($route) && trim($route) !== '')
            ->values();

        $missing = [];
        $unresolvable = [];

        foreach ($routes as $routeName) {
            if (! Route::has($routeName)) {
                $missing[] = $routeName;

                continue;
            }

            try {
                route($routeName, [], false);
            } catch (Throwable) {
                $unresolvable[] = $routeName;
            }
        }

        $detailParts = [];

        if ($missing !== []) {
            $detailParts[] = 'Missing routes: '.implode(', ', $missing);
        }

        if ($unresolvable !== []) {
            $detailParts[] = 'Unresolvable routes: '.implode(', ', $unresolvable);
        }

        if ($detailParts === []) {
            $detailParts[] = 'All critical deploy routes are registered.';
        }

        return [
            'key' => 'critical_routes',
            'label' => 'Critical routes',
            'ok' => $missing === [] && $unresolvable === [],
            'detail' => implode(' | ', $detailParts),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queueInfrastructureCheck(): array
    {
        $requiredTables = [
            (string) config('queue.connections.database.table', 'jobs'),
            (string) config('queue.failed.table', 'failed_jobs'),
            'job_batches',
        ];

        try {
            $missing = collect($requiredTables)
                ->reject(fn (string $table) => DB::getSchemaBuilder()->hasTable($table))
                ->values();

            return [
                'key' => 'queue_infrastructure',
                'label' => 'Queue infrastructure',
                'ok' => $missing->isEmpty(),
                'detail' => $missing->isEmpty()
                    ? 'Jobs, failed jobs, and batch tables are present.'
                    : 'Missing queue tables: '.$missing->implode(', '),
            ];
        } catch (Throwable $exception) {
            return [
                'key' => 'queue_infrastructure',
                'label' => 'Queue infrastructure',
                'ok' => false,
                'detail' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function superAdminPresenceCheck(): array
    {
        $count = User::query()
            ->where('is_super_admin', true)
            ->count();

        return [
            'key' => 'super_admin_presence',
            'label' => 'Platform admins present',
            'ok' => $count > 0,
            'detail' => $count > 0
                ? "Found {$count} platform admin account(s)."
                : 'No platform admin accounts found.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activeCompanyPresenceCheck(): array
    {
        $count = Company::query()
            ->where('is_active', true)
            ->count();

        return [
            'key' => 'active_company_presence',
            'label' => 'Active companies present',
            'ok' => $count > 0,
            'detail' => $count > 0
                ? "Found {$count} active compan(ies)."
                : 'No active companies found.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schedulerHeartbeatCheck(bool $required): array
    {
        $status = $this->operationalAlertingService->getStatus();
        $heartbeat = (array) ($status['heartbeat'] ?? []);
        $lastSeenAt = $heartbeat['last_seen_at'] ?? null;
        $minutesSince = $heartbeat['minutes_since'] ?? null;
        $isStale = (bool) ($heartbeat['is_stale'] ?? true);

        if (! $required && ! $lastSeenAt) {
            return [
                'key' => 'scheduler_heartbeat',
                'label' => 'Scheduler heartbeat',
                'ok' => true,
                'detail' => 'No heartbeat recorded yet; rerun with --require-heartbeat after scheduler restart if needed.',
            ];
        }

        return [
            'key' => 'scheduler_heartbeat',
            'label' => 'Scheduler heartbeat',
            'ok' => ! $isStale,
            'detail' => ! $isStale
                ? "Last heartbeat recorded {$minutesSince} minute(s) ago."
                : ($lastSeenAt
                    ? "Scheduler heartbeat is stale ({$minutesSince} minute(s) old)."
                    : 'Scheduler heartbeat has not been recorded yet.'),
        ];
    }
}
