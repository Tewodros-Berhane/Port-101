<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

test('recovery signoff command validates the latest restore workspace and writes an artifact', function () {
    $root = storage_path('framework/testing/recovery-signoff/'.uniqid());
    $workspace = $root.'/restore-drills/20260327-120000-demo';
    $outputDir = $root.'/signoffs';

    File::ensureDirectoryExists($workspace.'/backups/database');
    File::ensureDirectoryExists($workspace.'/backups/storage');
    File::ensureDirectoryExists($workspace.'/logs');

    File::put($workspace.'/backups/database/demo.dump', 'dump');
    File::put($workspace.'/backups/storage/demo.tar.gz', 'archive');
    File::put(
        $workspace.'/logs/recovery-smoke-check.json',
        json_encode([
            'ok' => true,
            'checks' => [
                ['label' => 'Database connection', 'ok' => true],
            ],
        ], JSON_PRETTY_PRINT)
    );
    File::put(
        $workspace.'/logs/deploy-smoke-check.json',
        json_encode([
            'ok' => true,
            'checks' => [
                ['label' => 'Platform admins present', 'ok' => true],
            ],
        ], JSON_PRETTY_PRINT)
    );

    config()->set('core.recovery.restore_drill_root', $root.'/restore-drills');
    config()->set('core.recovery.signoff_output_dir', $outputDir);

    Artisan::call('ops:recovery:signoff', ['--write' => true]);

    expect(Artisan::output())->toContain('Recovery sign-off')
        ->and(File::isDirectory($outputDir))->toBeTrue()
        ->and(collect(File::files($outputDir))->count())->toBe(1);
});

test('recovery signoff command fails when deploy evidence is missing', function () {
    $root = storage_path('framework/testing/recovery-signoff/'.uniqid());
    $workspace = $root.'/restore-drills/20260327-120500-broken';

    File::ensureDirectoryExists($workspace.'/backups/database');
    File::ensureDirectoryExists($workspace.'/backups/storage');
    File::ensureDirectoryExists($workspace.'/logs');

    File::put($workspace.'/backups/database/demo.dump', 'dump');
    File::put($workspace.'/backups/storage/demo.tar.gz', 'archive');
    File::put(
        $workspace.'/logs/recovery-smoke-check.json',
        json_encode(['ok' => true, 'checks' => []], JSON_PRETTY_PRINT)
    );

    config()->set('core.recovery.restore_drill_root', $root.'/restore-drills');
    config()->set('core.recovery.signoff_output_dir', $root.'/signoffs');

    $exitCode = Artisan::call('ops:recovery:signoff', [
        '--workspace' => $workspace,
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Deploy smoke check');
});
