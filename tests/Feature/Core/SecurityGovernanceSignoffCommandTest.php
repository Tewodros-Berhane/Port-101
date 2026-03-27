<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

test('security signoff command validates evidence and writes an artifact', function () {
    $root = storage_path('framework/testing/security-signoff/'.uniqid());
    $evidenceFile = $root.'/security-evidence.json';
    $signoffDir = $root.'/signoffs';

    File::ensureDirectoryExists($root);
    File::put(
        $evidenceFile,
        json_encode([
            'environment' => 'staging',
            'owner' => 'security-ops',
            'secret_store' => 'aws-secrets-manager://port101/staging',
            'webhook_rotation_evidence' => 'runbook://webhook-rotation-drill',
            'api_token_ownership_reference' => 'inventory://integration-token-owners',
            'credential_rotation_runbook_reference' => 'docs/secret-handling-and-rotation.md',
            'malware_scan_dependency_reference' => 'runbook://clamav-validation',
            'recovery_secret_reference' => 'runbook://restore-secret-access',
            'emergency_rotation_reference' => 'runbook://emergency-rotation',
            'staging_production_separated' => true,
            'secret_manager_backed' => true,
            'developer_workstation_independent' => true,
            'rotation_cadence_defined' => true,
        ], JSON_PRETTY_PRINT)
    );

    config()->set('core.security.signoff_output_dir', $signoffDir);

    $exitCode = Artisan::call('ops:security:signoff', [
        'evidenceFile' => $evidenceFile,
        '--write' => true,
    ]);

    expect($exitCode)->toBe(0)
        ->and(File::isDirectory($signoffDir))->toBeTrue()
        ->and(collect(File::files($signoffDir))->count())->toBe(1);
});

test('security signoff command fails when evidence is incomplete', function () {
    $root = storage_path('framework/testing/security-signoff/'.uniqid());
    $evidenceFile = $root.'/security-evidence-invalid.json';

    File::ensureDirectoryExists($root);
    File::put(
        $evidenceFile,
        json_encode([
            'environment' => 'staging',
            'secret_manager_backed' => false,
        ], JSON_PRETTY_PRINT)
    );

    $exitCode = Artisan::call('ops:security:signoff', [
        'evidenceFile' => $evidenceFile,
    ]);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Security governance sign-off')
        ->and($output)->toContain('Owner')
        ->and($output)->toContain('Secrets stored in approved secret manager');
});
