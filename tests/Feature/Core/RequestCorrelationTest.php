<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Fixtures\Jobs\CaptureStructuredLogContextJob;

test('web and api responses emit request correlation ids', function () {
    $webResponse = $this->get('/login')
        ->assertOk();

    $webRequestId = $webResponse->headers->get('X-Request-Id');

    expect($webRequestId)->not->toBeNull();
    expect(Str::isUuid($webRequestId))->toBeTrue();

    $apiRequestId = (string) Str::uuid();

    $this->getJson('/api/v1/health', [
        'X-Request-Id' => $apiRequestId,
    ])
        ->assertOk()
        ->assertHeader('X-Request-Id', $apiRequestId);
});

test('queued jobs inherit request correlation ids from the originating request', function () {
    CaptureStructuredLogContextJob::$capturedContext = [];

    Route::middleware('web')->get('/_test/request-correlation-queue', function () {
        CaptureStructuredLogContextJob::dispatch();

        return response()->json(['ok' => true]);
    })->name('testing.request-correlation-queue');

    $requestId = (string) Str::uuid();

    $this->get('/_test/request-correlation-queue', [
        'X-Request-Id' => $requestId,
    ])
        ->assertOk()
        ->assertHeader('X-Request-Id', $requestId);

    expect(CaptureStructuredLogContextJob::$capturedContext)->not->toBeEmpty();
    expect(CaptureStructuredLogContextJob::$capturedContext)->toMatchArray([
        'runtime' => 'queue',
        'request_id' => $requestId,
        'correlation_origin' => 'http',
    ]);
});
