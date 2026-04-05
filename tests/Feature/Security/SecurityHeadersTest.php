<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('unauthenticated web responses include security headers and nonce-based csp', function () {
    $response = get(route('home'));

    $response
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader(
            'Permissions-Policy',
            'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()'
        );

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("base-uri 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toContain("script-src 'self' 'nonce-")
        ->toContain("style-src 'self' 'nonce-");

    expect((string) $response->getContent())->toContain('nonce="');
    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

test('authenticated web responses include security headers', function () {
    [$user] = makeActiveCompanyMember(
        User::factory()->create(['email_verified_at' => now()])
    );

    $response = actingAs($user)->get(route('company.dashboard'));

    $response
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    expect((string) $response->headers->get('Content-Security-Policy'))
        ->toContain("frame-ancestors 'none'");
});

test('secure production-style requests include hsts when enabled', function () {
    config()->set('security.hsts.enabled', true);
    config()->set('security.hsts.max_age', 31536000);
    config()->set('security.hsts.include_subdomains', true);
    config()->set('security.hsts.preload', false);

    $response = $this
        ->withServerVariables([
            'HTTPS' => 'on',
            'SERVER_PORT' => 443,
        ])
        ->get('https://localhost/');

    $response->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

test('insecure requests do not receive hsts even when enabled', function () {
    config()->set('security.hsts.enabled', true);

    $response = get(route('home'));

    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});
