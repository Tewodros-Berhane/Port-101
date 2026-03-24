<?php

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCompanyWorkflowSeeder;

use function Pest\Laravel\artisan;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoCompanyWorkflowSeeder::class);
});

test('seeded integration smoke check validates demo workflow data', function () {
    artisan('ops:integration:smoke-check', ['--company' => 'demo-company-workflow'])
        ->expectsOutputToContain('Seeded integration smoke check')
        ->expectsOutputToContain('Sales workflow baseline')
        ->expectsOutputToContain('Accounting workflow baseline')
        ->assertSuccessful();
});

test('seeded integration smoke check emits json output', function () {
    artisan('ops:integration:smoke-check', [
        '--company' => 'demo-company-workflow',
        '--json' => true,
    ])
        ->expectsOutputToContain('"ok": true')
        ->assertSuccessful();
});
