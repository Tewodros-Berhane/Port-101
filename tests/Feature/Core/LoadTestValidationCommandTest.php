<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

test('load validation command accepts a passing k6 summary and writes signoff evidence', function () {
    $root = storage_path('framework/testing/load-validation/'.uniqid());
    $summaryFile = $root.'/load-summary.json';
    $signoffDir = $root.'/signoffs';

    File::ensureDirectoryExists($root);
    File::put(
        $summaryFile,
        json_encode([
            'metrics' => [
                'http_req_failed' => ['values' => ['rate' => 0.01]],
                'http_req_duration' => ['values' => ['p(95)' => 1200]],
                'health_success' => ['values' => ['rate' => 1]],
                'projects_success' => ['values' => ['rate' => 0.98]],
                'inventory_stock_balances_success' => ['values' => ['rate' => 0.98]],
                'sales_orders_success' => ['values' => ['rate' => 0.97]],
                'webhook_endpoints_success' => ['values' => ['rate' => 0.97]],
            ],
        ], JSON_PRETTY_PRINT)
    );

    config()->set('core.performance.load_signoff_output_dir', $signoffDir);

    $exitCode = Artisan::call('ops:performance:validate-load', [
        'summaryFile' => $summaryFile,
        '--write' => true,
    ]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Load-test validation')
        ->and(File::isDirectory($signoffDir))->toBeTrue()
        ->and(collect(File::files($signoffDir))->count())->toBe(1);
});

test('load validation command fails when the summary breaches thresholds', function () {
    $root = storage_path('framework/testing/load-validation/'.uniqid());
    $summaryFile = $root.'/load-summary-failing.json';

    File::ensureDirectoryExists($root);
    File::put(
        $summaryFile,
        json_encode([
            'metrics' => [
                'http_req_failed' => ['values' => ['rate' => 0.25]],
                'http_req_duration' => ['values' => ['p(95)' => 3200]],
                'health_success' => ['values' => ['rate' => 0.8]],
                'projects_success' => ['values' => ['rate' => 0.8]],
                'inventory_stock_balances_success' => ['values' => ['rate' => 0.8]],
                'sales_orders_success' => ['values' => ['rate' => 0.8]],
                'webhook_endpoints_success' => ['values' => ['rate' => 0.8]],
            ],
        ], JSON_PRETTY_PRINT)
    );

    $exitCode = Artisan::call('ops:performance:validate-load', [
        'summaryFile' => $summaryFile,
    ]);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('HTTP request failure rate')
        ->and($output)->toContain('HTTP request duration p95');
});
