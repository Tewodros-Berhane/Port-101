<?php

namespace App\Core\Platform;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RecoverySignoffService
{
    /**
     * @return array<string, mixed>
     */
    public function configSummary(?string $workspace = null): array
    {
        return [
            'restore_drill_root' => (string) config('core.recovery.restore_drill_root'),
            'signoff_output_dir' => (string) config('core.recovery.signoff_output_dir'),
            'selected_workspace' => $workspace,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function run(?string $workspace = null, bool $writeArtifact = false): array
    {
        $resolvedWorkspace = $this->resolveWorkspace($workspace);
        $recoveryReport = $this->readJsonReport($resolvedWorkspace, 'logs/recovery-smoke-check.json');
        $deployReport = $this->readJsonReport($resolvedWorkspace, 'logs/deploy-smoke-check.json');

        $checks = [
            $this->workspaceCheck($resolvedWorkspace),
            $this->backupArtifactCheck($resolvedWorkspace),
            $this->reportCheck('recovery_smoke_check', 'Recovery smoke check', $recoveryReport),
            $this->reportCheck('deploy_smoke_check', 'Deploy smoke check', $deployReport),
        ];

        $result = [
            'ok' => collect($checks)->every(fn (array $check) => (bool) ($check['ok'] ?? false)),
            'generated_at' => now()->toIso8601String(),
            'workspace' => $resolvedWorkspace,
            'checks' => $checks,
            'reports' => [
                'recovery' => $recoveryReport['report'] ?? null,
                'deploy' => $deployReport['report'] ?? null,
            ],
            'config' => $this->configSummary($resolvedWorkspace),
            'artifact_path' => null,
        ];

        if ($writeArtifact && $result['ok']) {
            $result['artifact_path'] = $this->writeArtifact($result);
        }

        return $result;
    }

    private function resolveWorkspace(?string $workspace = null): ?string
    {
        if (is_string($workspace) && trim($workspace) !== '') {
            return $workspace;
        }

        $root = (string) config('core.recovery.restore_drill_root');

        if (! File::isDirectory($root)) {
            return null;
        }

        $directories = collect(File::directories($root))
            ->sort()
            ->values();

        return $directories->last();
    }

    /**
     * @return array<string, mixed>
     */
    private function workspaceCheck(?string $workspace): array
    {
        if (! is_string($workspace) || trim($workspace) === '') {
            return [
                'key' => 'workspace',
                'label' => 'Restore workspace',
                'ok' => false,
                'detail' => 'No restore-drill workspace was resolved.',
            ];
        }

        return [
            'key' => 'workspace',
            'label' => 'Restore workspace',
            'ok' => File::isDirectory($workspace),
            'detail' => File::isDirectory($workspace)
                ? "Using restore-drill workspace [{$workspace}]."
                : "Restore-drill workspace [{$workspace}] does not exist.",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function backupArtifactCheck(?string $workspace): array
    {
        if (! is_string($workspace) || ! File::isDirectory($workspace)) {
            return [
                'key' => 'backup_artifacts',
                'label' => 'Backup artifacts',
                'ok' => false,
                'detail' => 'Restore-drill workspace is unavailable.',
            ];
        }

        $databaseArtifacts = collect(File::glob($workspace.'/backups/database/*'))
            ->filter(fn ($path) => File::isFile($path))
            ->values();
        $storageArtifacts = collect(File::glob($workspace.'/backups/storage/*'))
            ->filter(fn ($path) => File::isFile($path))
            ->values();

        return [
            'key' => 'backup_artifacts',
            'label' => 'Backup artifacts',
            'ok' => $databaseArtifacts->isNotEmpty() && $storageArtifacts->isNotEmpty(),
            'detail' => sprintf(
                'Database artifacts: %d | Storage artifacts: %d',
                $databaseArtifacts->count(),
                $storageArtifacts->count(),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $reportState
     * @return array<string, mixed>
     */
    private function reportCheck(string $key, string $label, array $reportState): array
    {
        if (! ($reportState['exists'] ?? false)) {
            return [
                'key' => $key,
                'label' => $label,
                'ok' => false,
                'detail' => $reportState['detail'] ?? 'Report is missing.',
            ];
        }

        if (! ($reportState['valid_json'] ?? false)) {
            return [
                'key' => $key,
                'label' => $label,
                'ok' => false,
                'detail' => $reportState['detail'] ?? 'Report JSON is invalid.',
            ];
        }

        $report = $reportState['report'] ?? [];
        $ok = (bool) ($report['ok'] ?? false);
        $failedChecks = collect($report['checks'] ?? [])
            ->filter(fn ($check) => ! (bool) ($check['ok'] ?? false))
            ->pluck('label')
            ->filter()
            ->values();

        return [
            'key' => $key,
            'label' => $label,
            'ok' => $ok,
            'detail' => $ok
                ? 'Report passed.'
                : ($failedChecks->isNotEmpty()
                    ? 'Failed checks: '.$failedChecks->implode(', ')
                    : 'Report exists but did not pass.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonReport(?string $workspace, string $relativePath): array
    {
        if (! is_string($workspace) || ! File::isDirectory($workspace)) {
            return [
                'exists' => false,
                'valid_json' => false,
                'detail' => 'Restore-drill workspace is unavailable.',
                'report' => null,
            ];
        }

        $path = $workspace.'/'.$relativePath;

        if (! File::exists($path)) {
            return [
                'exists' => false,
                'valid_json' => false,
                'detail' => "Missing report [{$relativePath}].",
                'report' => null,
            ];
        }

        $decoded = json_decode((string) File::get($path), true);

        if (! is_array($decoded)) {
            return [
                'exists' => true,
                'valid_json' => false,
                'detail' => "Report [{$relativePath}] is not valid JSON.",
                'report' => null,
            ];
        }

        return [
            'exists' => true,
            'valid_json' => true,
            'detail' => "Report [{$relativePath}] is valid.",
            'report' => $decoded,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function writeArtifact(array $result): string
    {
        $outputDir = (string) config('core.recovery.signoff_output_dir');
        File::ensureDirectoryExists($outputDir);

        $workspaceSlug = $result['workspace']
            ? Str::slug(basename((string) $result['workspace']))
            : 'unknown-workspace';
        $path = $outputDir.'/restore-signoff-'.now()->format('Ymd-His').'-'.$workspaceSlug.'.json';

        File::put(
            $path,
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        return $path;
    }
}
