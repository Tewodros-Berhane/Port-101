<?php

use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;

beforeEach(function () {
    $basePath = storage_path('framework/testing/backup-smoke');

    File::deleteDirectory($basePath);

    config()->set('core.backup.database_dump_dir', $basePath.'/database');
    config()->set('core.backup.storage_archive_dir', $basePath.'/storage');
});

test('recovery smoke check command validates the current environment', function () {
    artisan('ops:recovery:smoke-check')
        ->expectsOutputToContain('Recovery smoke check')
        ->expectsOutputToContain('Database connection')
        ->expectsOutputToContain('Pending migrations')
        ->assertSuccessful();

    expect(File::isDirectory((string) config('core.backup.database_dump_dir')))->toBeTrue()
        ->and(File::isDirectory((string) config('core.backup.storage_archive_dir')))->toBeTrue();
});

test('recovery smoke check command emits json output', function () {
    artisan('ops:recovery:smoke-check', ['--json' => true])
        ->expectsOutputToContain('"ok": true')
        ->assertSuccessful();
});
