<?php

namespace App\Modules\Integrations;

use Illuminate\Support\Str;

class WebhookTargetSecurityService
{
    public function validationError(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return 'Webhook target URL must include a valid host.';
        }

        if ($this->requireHttps() && $scheme !== 'https') {
            return 'Webhook endpoints must use HTTPS.';
        }

        if ($this->isBlockedHostname($host)) {
            return 'Webhook endpoints cannot target localhost or local-only hostnames.';
        }

        if (
            ! $this->allowPrivateTargets()
            && $this->isPrivateOrReservedIp($host)
        ) {
            return 'Webhook endpoints cannot target private or reserved IP ranges.';
        }

        return null;
    }

    private function isBlockedHostname(string $host): bool
    {
        $blockedHostnames = collect((array) config('core.webhooks.blocked_hostnames', []))
            ->map(fn ($value) => strtolower((string) $value))
            ->all();
        $blockedSuffixes = collect((array) config('core.webhooks.blocked_host_suffixes', []))
            ->map(fn ($value) => strtolower((string) $value))
            ->all();

        if (in_array($host, $blockedHostnames, true)) {
            return true;
        }

        return collect($blockedSuffixes)
            ->contains(fn (string $suffix) => Str::endsWith($host, $suffix));
    }

    private function isPrivateOrReservedIp(string $host): bool
    {
        if (! filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }

    private function requireHttps(): bool
    {
        $configured = config('core.webhooks.require_https');

        if ($configured === null || $configured === '') {
            return app()->isProduction();
        }

        return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
    }

    private function allowPrivateTargets(): bool
    {
        $configured = config('core.webhooks.allow_private_targets');

        if ($configured === null || $configured === '') {
            return app()->environment(['local', 'testing']);
        }

        return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
    }
}
