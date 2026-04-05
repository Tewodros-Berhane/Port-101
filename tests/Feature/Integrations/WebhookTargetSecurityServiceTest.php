<?php

use App\Modules\Integrations\WebhookTargetSecurityService;
use Illuminate\Support\Facades\Config;

function fakeWebhookTargetSecurityService(array $resolvedIpsByHost): WebhookTargetSecurityService
{
    return new class($resolvedIpsByHost) extends WebhookTargetSecurityService
    {
        /**
         * @param  array<string, array<int, string>>  $resolvedIpsByHost
         */
        public function __construct(
            private readonly array $resolvedIpsByHost,
        ) {}

        /**
         * @return array<int, string>
         */
        protected function resolveHostIps(string $host): array
        {
            if (array_key_exists($host, $this->resolvedIpsByHost)) {
                return $this->resolvedIpsByHost[$host];
            }

            return parent::resolveHostIps($host);
        }
    };
}

beforeEach(function () {
    Config::set('core.webhooks.require_https', true);
    Config::set('core.webhooks.allow_private_targets', false);
});

test('webhook target security service rejects localhost, private, and reserved targets', function () {
    $service = fakeWebhookTargetSecurityService([]);

    expect($service->validationError('https://localhost/hooks', 'Webhook target URL'))
        ->toBe('Webhook target URL cannot target localhost or local-only hostnames.');

    expect($service->validationError('https://10.10.10.10/hooks', 'Webhook target URL'))
        ->toBe('Webhook target URL cannot resolve to private or reserved IP ranges.');

    expect($service->validationError('https://169.254.169.254/hooks', 'Webhook target URL'))
        ->toBe('Webhook target URL cannot resolve to private or reserved IP ranges.');
});

test('webhook target security service allows public targets and rejects private-resolving hostnames', function () {
    $service = fakeWebhookTargetSecurityService([
        'public-hooks.example.com' => ['93.184.216.34'],
        'private-hooks.example.com' => ['10.0.0.20'],
    ]);

    expect($service->validationError('https://public-hooks.example.com/hooks', 'Webhook target URL'))
        ->toBeNull();

    expect($service->validationError('https://private-hooks.example.com/hooks', 'Webhook target URL'))
        ->toBe('Webhook target URL cannot resolve to private or reserved IP ranges.');
});

test('webhook target security service rejects unresolved hostnames in hardened mode', function () {
    $service = fakeWebhookTargetSecurityService([
        'unresolved-hooks.example.com' => [],
    ]);

    expect($service->validationError('https://unresolved-hooks.example.com/hooks', 'Webhook target URL'))
        ->toBe('Webhook target URL must resolve to a public IP address.');
});

