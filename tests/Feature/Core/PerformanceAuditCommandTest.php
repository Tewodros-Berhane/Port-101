<?php

use function Pest\Laravel\artisan;

test('performance audit validates the current index baseline', function () {
    artisan('ops:performance:audit')
        ->expectsOutputToContain('Performance audit')
        ->expectsOutputToContain('Queue jobs')
        ->expectsOutputToContain('Webhook deliveries')
        ->assertSuccessful();
});

test('performance audit emits json output', function () {
    artisan('ops:performance:audit', ['--json' => true])
        ->expectsOutputToContain('"ok": true')
        ->assertSuccessful();
});
