<?php

namespace App\Core\Platform;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class RecoverySmokeCheckService
{
    /**
     * @return array<string, mixed>
     */
    public function configSummary(): array
    {
        $attachmentsDisk = (string) config('core.backup.attachments_disk', 'local');

        return [
            'database_connection' => (string) config('database.default'),
            'database_name' => (string) config('database.connections.'.config('database.default').'.database'),
            'attachments_disk' => $attachmentsDisk,
            'attachments_root' => config('filesystems.disks.'.$attachmentsDisk.'.root'),
            'database_dump_dir' => (string) config('core.backup.database_dump_dir'),
            'storage_archive_dir' => (string) config('core.backup.storage_archive_dir'),
            'retention_days' => (int) config('core.backup.retention_days', 14),
            'local_storage_paths' => (array) config('core.backup.local_storage_paths', []),
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     checks: array<int, array<string, mixed>>,
     *     config: array<string, mixed>
     * }
     */
    public function run(): array
    {
        $checks = [
            $this->databaseConnectionCheck(),
            $this->pendingMigrationsCheck(),
            $this->attachmentsDiskWriteCheck(),
            $this->backupDirectoriesCheck(),
            $this->criticalTablesCheck(),
        ];

        return [
            'ok' => collect($checks)->every(fn (array $check) => (bool) ($check['ok'] ?? false)),
            'checks' => $checks,
            'config' => $this->configSummary(),
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
                'detail' => 'PostgreSQL connection responded successfully.',
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
    private function attachmentsDiskWriteCheck(): array
    {
        $disk = (string) config('core.backup.attachments_disk', 'local');
        $path = 'recovery-smoke-check/'.Str::uuid().'.txt';

        try {
            Storage::disk($disk)->put($path, 'port-101 recovery smoke check');

            $written = Storage::disk($disk)->exists($path)
                && Storage::disk($disk)->get($path) === 'port-101 recovery smoke check';

            Storage::disk($disk)->delete($path);

            return [
                'key' => 'attachments_disk_write',
                'label' => 'Attachments disk read/write',
                'ok' => $written,
                'detail' => $written
                    ? "Disk [{$disk}] accepted write/read/delete."
                    : "Disk [{$disk}] failed round-trip verification.",
            ];
        } catch (Throwable $exception) {
            return [
                'key' => 'attachments_disk_write',
                'label' => 'Attachments disk read/write',
                'ok' => false,
                'detail' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function backupDirectoriesCheck(): array
    {
        $databaseDir = (string) config('core.backup.database_dump_dir');
        $storageDir = (string) config('core.backup.storage_archive_dir');

        try {
            File::ensureDirectoryExists($databaseDir);
            File::ensureDirectoryExists($storageDir);

            $databaseWritable = is_dir($databaseDir) && is_writable($databaseDir);
            $storageWritable = is_dir($storageDir) && is_writable($storageDir);

            return [
                'key' => 'backup_directories',
                'label' => 'Backup directories',
                'ok' => $databaseWritable && $storageWritable,
                'detail' => sprintf(
                    'DB dump dir: %s | Storage archive dir: %s',
                    $databaseWritable ? 'ready' : 'not writable',
                    $storageWritable ? 'ready' : 'not writable',
                ),
            ];
        } catch (Throwable $exception) {
            return [
                'key' => 'backup_directories',
                'label' => 'Backup directories',
                'ok' => false,
                'detail' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function criticalTablesCheck(): array
    {
        $requiredTables = [
            'migrations',
            'jobs',
            'failed_jobs',
            'audit_logs',
            'attachments',
        ];

        try {
            $missing = collect($requiredTables)
                ->reject(fn (string $table) => DB::getSchemaBuilder()->hasTable($table))
                ->values();

            return [
                'key' => 'critical_tables',
                'label' => 'Critical tables',
                'ok' => $missing->isEmpty(),
                'detail' => $missing->isEmpty()
                    ? 'All critical tables are present.'
                    : 'Missing tables: '.$missing->implode(', '),
            ];
        } catch (Throwable $exception) {
            return [
                'key' => 'critical_tables',
                'label' => 'Critical tables',
                'ok' => false,
                'detail' => $exception->getMessage(),
            ];
        }
    }
}
