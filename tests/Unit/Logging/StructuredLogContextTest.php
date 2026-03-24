<?php

use App\Support\Logging\StructuredLogContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

test('structured log context derives request fields from named web routes', function () {
    $context = new StructuredLogContext;

    $request = Request::create('/company/integrations/webhooks/endpoint-1', 'GET');
    $request->setUserResolver(fn () => (object) [
        'id' => 'user-123',
        'current_company_id' => 'company-456',
    ]);

    $route = new Route(['GET'], '/company/integrations/webhooks/{endpoint}', []);
    $route->name('company.integrations.webhooks.show');
    $route->bind($request);

    $request->setRouteResolver(fn () => $route);

    $context->setRequestContext($request);

    expect($context->all())->toMatchArray([
        'runtime' => 'http',
        'request_method' => 'GET',
        'request_path' => '/company/integrations/webhooks/endpoint-1',
        'route_name' => 'company.integrations.webhooks.show',
        'route_uri' => 'company/integrations/webhooks/{endpoint}',
        'user_id' => 'user-123',
        'company_id' => 'company-456',
        'module' => 'integrations',
        'entity' => 'webhooks',
        'action' => 'show',
    ]);
});

test('structured log context derives request fields from api paths without named routes', function () {
    $context = new StructuredLogContext;

    $request = Request::create('/api/v1/sales/orders/123/confirm', 'POST');
    $request->setUserResolver(fn () => null);
    $request->setRouteResolver(fn () => null);

    $context->setRequestContext($request);

    expect($context->all())->toMatchArray([
        'runtime' => 'http',
        'request_method' => 'POST',
        'request_path' => '/api/v1/sales/orders/123/confirm',
        'module' => 'sales',
        'entity' => 'orders',
        'action' => 'confirm',
    ]);
});

test('structured log context stores and clears queue scope independently', function () {
    $context = new StructuredLogContext;

    $context->setQueueContext([
        'job_name' => 'App\\Jobs\\DeliverWebhook',
        'queue_name' => 'default',
        'job_id' => '123',
    ]);

    expect($context->all())->toMatchArray([
        'runtime' => 'queue',
        'job_name' => 'App\\Jobs\\DeliverWebhook',
        'queue_name' => 'default',
        'job_id' => '123',
    ]);

    $context->clearScope('queue');

    expect($context->all())->not->toHaveKey('job_name');
    expect($context->all())->not->toHaveKey('queue_name');
});
