<?php

namespace App\Core\Platform;

use Illuminate\Support\Facades\File;

class SecurityGovernanceSignoffService
{
    /**
     * @return array<string, mixed>
     */
    public function configSummary(): array
    {
        return [
            'signoff_output_dir' => (string) config('core.security.signoff_output_dir'),
            'checklist_path' => base_path('docs/secret-handling-and-rotation.md'),
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
            $this->stringCheck('secret_store', 'Secret store', $evidence['secret_store'] ?? null),
            $this->stringCheck('webhook_rotation_evidence', 'Webhook rotation evidence', $evidence['webhook_rotation_evidence'] ?? null),
            $this->stringCheck('api_token_ownership_reference', 'API token ownership reference', $evidence['api_token_ownership_reference'] ?? null),
            $this->stringCheck('credential_rotation_runbook_reference', 'Credential rotation runbook reference', $evidence['credential_rotation_runbook_reference'] ?? null),
            $this->stringCheck('malware_scan_dependency_reference', 'Malware scanning dependency reference', $evidence['malware_scan_dependency_reference'] ?? null),
            $this->stringCheck('recovery_secret_reference', 'Recovery secret reference', $evidence['recovery_secret_reference'] ?? null),
            $this->stringCheck('emergency_rotation_reference', 'Emergency rotation reference', $evidence['emergency_rotation_reference'] ?? null),
            $this->booleanCheck('staging_production_separated', 'Staging and production credentials separated', $evidence['staging_production_separated'] ?? null),
            $this->booleanCheck('secret_manager_backed', 'Secrets stored in approved secret manager', $evidence['secret_manager_backed'] ?? null),
            $this->booleanCheck('developer_workstation_independent', 'Secrets do not depend on developer workstations', $evidence['developer_workstation_independent'] ?? null),
            $this->booleanCheck('rotation_cadence_defined', 'Rotation cadence defined', $evidence['rotation_cadence_defined'] ?? null),
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
        $outputDir = (string) config('core.security.signoff_output_dir');
        File::ensureDirectoryExists($outputDir);

        $path = $outputDir.'/security-signoff-'.now()->format('Ymd-His').'.json';

        File::put(
            $path,
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        return $path;
    }
}
