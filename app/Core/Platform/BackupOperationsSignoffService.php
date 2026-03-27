<?php

namespace App\Core\Platform;

use Illuminate\Support\Facades\File;

class BackupOperationsSignoffService
{
    /**
     * @return array<string, mixed>
     */
    public function configSummary(): array
    {
        return [
            'signoff_output_dir' => (string) config('core.backup.signoff_output_dir'),
            'checklist_path' => base_path('docs/backup-scheduling-and-retention.md'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function run(string $evidenceFile, bool $writeArtifact = false): array
    {
        $evidenceState = $this->readEvidence($evidenceFile);
        $evidence = (array) ($evidenceState['evidence'] ?? []);

        $checks = [
            $this->evidenceFileCheck($evidenceState),
            $this->stringCheck('environment', 'Environment', $evidence['environment'] ?? null),
            $this->stringCheck('owner', 'Owner', $evidence['owner'] ?? null),
            $this->stringCheck('database_backup_job_reference', 'Database backup job reference', $evidence['database_backup_job_reference'] ?? null),
            $this->stringCheck('storage_backup_job_reference', 'Storage backup job reference', $evidence['storage_backup_job_reference'] ?? null),
            $this->stringCheck('database_backup_artifact_reference', 'Database backup artifact reference', $evidence['database_backup_artifact_reference'] ?? null),
            $this->stringCheck('storage_backup_artifact_reference', 'Storage backup artifact reference', $evidence['storage_backup_artifact_reference'] ?? null),
            $this->stringCheck('retention_cleanup_reference', 'Retention cleanup reference', $evidence['retention_cleanup_reference'] ?? null),
            $this->stringCheck('restore_signoff_artifact', 'Restore sign-off artifact', $evidence['restore_signoff_artifact'] ?? null),
            $this->stringCheck('smoke_check_artifact', 'Smoke-check artifact', $evidence['smoke_check_artifact'] ?? null),
            $this->stringCheck('artifact_storage_location', 'Artifact storage location', $evidence['artifact_storage_location'] ?? null),
            $this->booleanCheck('encrypted_at_rest', 'Artifacts encrypted at rest', $evidence['encrypted_at_rest'] ?? null),
            $this->booleanCheck('stored_outside_primary_host', 'Artifacts stored outside primary host', $evidence['stored_outside_primary_host'] ?? null),
            $this->booleanCheck('backup_failure_alerting', 'Backup failure alerting', $evidence['backup_failure_alerting'] ?? null),
        ];

        $result = [
            'ok' => collect($checks)->every(fn (array $check) => (bool) ($check['ok'] ?? false)),
            'generated_at' => now()->toIso8601String(),
            'evidence_file' => $evidenceFile,
            'checks' => $checks,
            'evidence' => $evidenceState['evidence'] ?? null,
            'config' => $this->configSummary(),
            'artifact_path' => null,
        ];

        if ($writeArtifact && $result['ok']) {
            $result['artifact_path'] = $this->writeArtifact($result);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function readEvidence(string $evidenceFile): array
    {
        if (! File::exists($evidenceFile)) {
            return [
                'exists' => false,
                'valid_json' => false,
                'detail' => "Evidence file [{$evidenceFile}] does not exist.",
                'evidence' => null,
            ];
        }

        $decoded = json_decode((string) File::get($evidenceFile), true);

        if (! is_array($decoded)) {
            return [
                'exists' => true,
                'valid_json' => false,
                'detail' => "Evidence file [{$evidenceFile}] is not valid JSON.",
                'evidence' => null,
            ];
        }

        return [
            'exists' => true,
            'valid_json' => true,
            'detail' => "Evidence file [{$evidenceFile}] parsed successfully.",
            'evidence' => $decoded,
        ];
    }

    /**
     * @param  array<string, mixed>  $evidenceState
     * @return array<string, mixed>
     */
    private function evidenceFileCheck(array $evidenceState): array
    {
        return [
            'key' => 'evidence_file',
            'label' => 'Evidence file',
            'ok' => (bool) ($evidenceState['exists'] ?? false) && (bool) ($evidenceState['valid_json'] ?? false),
            'detail' => (string) ($evidenceState['detail'] ?? 'Evidence file validation failed.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stringCheck(string $key, string $label, mixed $value): array
    {
        $stringValue = is_string($value) ? trim($value) : '';

        return [
            'key' => $key,
            'label' => $label,
            'ok' => $stringValue !== '',
            'detail' => $stringValue !== ''
                ? $stringValue
                : 'Missing required evidence value.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function booleanCheck(string $key, string $label, mixed $value): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'ok' => $value === true,
            'detail' => $value === true
                ? 'Confirmed.'
                : 'Expected boolean true in the evidence file.',
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function writeArtifact(array $result): string
    {
        $outputDir = (string) config('core.backup.signoff_output_dir');
        File::ensureDirectoryExists($outputDir);

        $path = $outputDir.'/backup-signoff-'.now()->format('Ymd-His').'.json';

        File::put(
            $path,
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        return $path;
    }
}
