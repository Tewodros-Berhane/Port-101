<?php

use App\Core\Company\Models\Company;
use App\Core\Platform\PlatformOperationalAlertingService;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\artisan;

beforeEach(function () {
    User::factory()->create([
        'is_super_admin' => true,
    ]);

    $owner = User::factory()->create();
    $name = 'Deploy Smoke '.Str::upper(Str::random(4));

    Company::create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
        'timezone' => 'UTC',
        'is_active' => true,
        'owner_id' => $owner->id,
    ]);
});

test('deployment smoke check validates the current environment', function () {
    artisan('ops:deploy:smoke-check')
        ->expectsOutputToContain('Deployment smoke check')
        ->expectsOutputToContain('Critical routes')
        ->expectsOutputToContain('Queue infrastructure')
        ->assertSuccessful();
});

test('deployment smoke check emits json output', function () {
    artisan('ops:deploy:smoke-check', ['--json' => true])
        ->expectsOutputToContain('"ok": true')
        ->assertSuccessful();
});

test('deployment smoke check can require a scheduler heartbeat', function () {
    artisan('ops:deploy:smoke-check', ['--require-heartbeat' => true])
        ->expectsOutputToContain('Scheduler heartbeat')
        ->assertFailed();

    app(PlatformOperationalAlertingService::class)->recordSchedulerHeartbeat();

    artisan('ops:deploy:smoke-check', ['--require-heartbeat' => true])
        ->expectsOutputToContain('Scheduler heartbeat')
        ->assertSuccessful();
});
