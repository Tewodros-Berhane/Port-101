<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Database\QueryException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();

    Route::middleware('web')->group(function () {
        Route::get('/_test/errors/source', fn () => 'source');

        Route::get('/_test/errors/status/{status}', function (int $status) {
            abort($status);
        })->whereNumber('status');

        Route::post('/_test/errors/page-expired', function () {
            throw new TokenMismatchException('expired');
        });

        Route::get('/_test/errors/runtime', function () {
            throw new RuntimeException('runtime failure should not leak');
        });

        Route::get('/_test/errors/query', function () {
            throw new QueryException(
                'sqlite',
                'select * from users where email = ?',
                ['leak@example.com'],
                new Exception('SQLSTATE[HY000]: General error'),
            );
        });
    });

    Route::middleware('api')->prefix('api/v1')->group(function () {
        Route::get('/_test/errors/runtime', function () {
            throw new RuntimeException('api runtime failure should not leak');
        });
    });
});

dataset('handledErrorStatuses', [
    [400, 'Request could not be completed'],
    [401, 'Sign in required'],
    [403, 'Access denied'],
    [405, 'Action not available'],
    [419, 'Page expired'],
    [422, 'Request could not be completed'],
    [429, 'Too many requests'],
    [503, 'Service temporarily unavailable'],
]);

it('renders branded inertia pages for handled web statuses', function (int $status, string $title): void {
    config(['app.debug' => false]);

    $response = $this->get("/_test/errors/status/{$status}");

    $response
        ->assertStatus($status)
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', $status)
            ->where('title', $title)
            ->has('actions')
        );
})->with('handledErrorStatuses');

it('renders a branded 404 page for missing routes', function (): void {
    config(['app.debug' => false]);

    $response = $this->get('/missing-route-for-error-test');

    $response
        ->assertStatus(404)
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', 404)
            ->where('title', 'Page not found')
        );
});

it('returns a branded inertia payload for x-inertia navigation failures', function (): void {
    config(['app.debug' => false]);

    $version = app(HandleInertiaRequests::class)->version(request());

    $response = $this
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version ?? '',
        ])
        ->get('/_test/errors/status/403');

    $response
        ->assertStatus(403)
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'error')
        ->assertJsonPath('props.status', 403)
        ->assertJsonPath('props.title', 'Access denied');
});

it('redirects back with a warning flash for expired inertia form submissions', function (): void {
    config(['app.debug' => false]);

    $response = $this
        ->from('/_test/errors/source')
        ->withHeader('X-Inertia', 'true')
        ->post('/_test/errors/page-expired');

    $response
        ->assertRedirect('/_test/errors/source')
        ->assertSessionHas('warning', 'Your session or form token expired before the request finished.');
});

it('renders safe json for unexpected non-api json requests', function (): void {
    config(['app.debug' => true]);

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->get('/_test/errors/runtime');

    $response
        ->assertStatus(500)
        ->assertJsonPath('message', 'The server hit an unexpected problem while processing your request.')
        ->assertJsonStructure([
            'message',
            'meta' => ['reference_id'],
        ]);

    expect($response->getContent())->not->toContain('runtime failure should not leak');
});

it('renders a safe branded 500 page for database exceptions without leaking sql details', function (): void {
    config(['app.debug' => true]);

    $response = $this->get('/_test/errors/query');

    $response
        ->assertStatus(500)
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', 500)
            ->where('title', 'Something went wrong')
            ->where('message', 'The server hit an unexpected problem while processing your request.')
            ->where('actions.1.label', 'Get help')
            ->where('actions.1.href', route('public.contact-sales'))
        );

    expect($response->getContent())
        ->not->toContain('select * from users where email = ?')
        ->not->toContain('leak@example.com')
        ->not->toContain('SQLSTATE');
});

it('includes a real help action on 503 pages', function (): void {
    config(['app.debug' => false]);

    $response = $this->get('/_test/errors/status/503');

    $response
        ->assertStatus(503)
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', 503)
            ->where('actions.1.label', 'Get help')
            ->where('actions.1.href', route('public.contact-sales'))
        );
});

it('keeps api v1 failures in safe json format', function (): void {
    config(['app.debug' => true]);

    $response = $this->getJson('/api/v1/_test/errors/runtime');

    $response
        ->assertStatus(500)
        ->assertJsonPath('message', 'The server hit an unexpected problem while processing your request.')
        ->assertJsonStructure([
            'message',
            'meta' => ['reference_id'],
        ]);

    expect($response->getContent())->not->toContain('api runtime failure should not leak');
});
