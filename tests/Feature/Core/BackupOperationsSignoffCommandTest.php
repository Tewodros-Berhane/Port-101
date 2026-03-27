<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

test('backup signoff command validates evidence and writes an artifact', function () {
    $root = storage_path('framework/testing/backup-signoff/'.uniqid());
    $evidenceFile = $root.'/backup-evidence.json';
    $signoffDir = $root.'/signoffs';

    File::ensureDirectoryExists($root);
    File::put(
        $evidenceFile,
        json_encode([
            'environment' => 'staging',
            'owner' => 'platform-ops',
            'database_backup_job_reference' => 'scheduler://database-backup-nightly',
            'storage_backup_job_reference' => 'scheduler://storage-backup-nightly',
            'database_backup_artifact_reference' => 's3://backups/database/latest.sql.gz',
            'storage_backup_artifact_reference' => 's3://backups/storage/latest.tar.gz',
            'retention_cleanup_reference' => 'job-run://backup-retention-nightly',
            'restore_signoff_artifact' => 'storage/app/recovery-signoffs/example.json',
            'smoke_check_artifact' => 'storage/app/restore-drills/example/logs/recovery-smoke-check.json',
            'artifact_storage_location' => 's3://backups/staging',
            'encrypted_at_rest' => true,
            'stored_outside_primary_host' => true,
            'backup_failure_alerting' => true,
        ], JSON_PRETTY_PRINT)
    );

    config()->set('core.backup.signoff_output_dir', $signoffDir);

    $exitCode = Artisan::call('ops:backup:signoff', [
        'evidenceFile' => $evidenceFile,
        '--write' => true,
    ]);

    expect($exitCode)->toBe(0)
        ->and(File::isDirectory($signoffDir))->toBeTrue()
        ->and(collect(File::files($signoffDir))->count())->toBe(1);
});

test('backup signoff command fails when evidence is incomplete', function () {
    $root = storage_path('framework/testing/backup-signoff/'.uniqid());
    $evidenceFile = $root.'/backup-evidence-invalid.json';

    File::ensureDirectoryExists($root);
    File::put(
        $evidenceFile,
        json_encode([
            'environment' => 'staging',
            'owner' => '',
            'encrypted_at_rest' => false,
        ], JSON_PRETTY_PRINT)
    );

    $exitCode = Artisan::call('ops:backup:signoff', [
        'evidenceFile' => $evidenceFile,
    ]);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Backup operations sign-off')
        ->and($output)->toContain('Owner')
        ->and($output)->toContain('Artifacts encrypted at rest');
});
