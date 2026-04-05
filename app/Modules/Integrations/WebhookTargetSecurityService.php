<?php

namespace App\Modules\Integrations;

use Illuminate\Support\Str;

class WebhookTargetSecurityService
{
    public function validationError(
        ?string $url,
        string $urlLabel = 'Webhook target URL',
    ): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return "{$urlLabel} must use a valid HTTP or HTTPS URL.";
        }

        if ($host === '') {
            return "{$urlLabel} must include a valid host.";
        }

        if ($this->requireHttps() && $scheme !== 'https') {
            return "{$urlLabel} must use HTTPS.";
        }

        if ($this->isBlockedHostname($host)) {
            return "{$urlLabel} cannot target localhost or local-only hostnames.";
        }

        if ($this->allowPrivateTargets()) {
            return null;
        }

        $resolvedIps = $this->resolveHostIps($host);

        if ($resolvedIps === []) {
            return "{$urlLabel} must resolve to a public IP address.";
        }

        if ($this->containsPrivateOrReservedIp($resolvedIps)) {
            return "{$urlLabel} cannot resolve to private or reserved IP ranges.";
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveHostIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $resolved = [];

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);

            if (is_array($records)) {
                foreach ($records as $record) {
                    $type = strtoupper((string) ($record['type'] ?? ''));

                    if ($type === 'A' && filter_var($record['ip'] ?? null, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $resolved[] = (string) $record['ip'];
                    }

                    if ($type === 'AAAA' && filter_var($record['ipv6'] ?? null, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        $resolved[] = strtolower((string) $record['ipv6']);
                    }
                }
            }
        }

        if ($resolved === []) {
            $fallback = @gethostbynamel($host);

            if (is_array($fallback)) {
                foreach ($fallback as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $resolved[] = (string) $ip;
                    }
                }
            }
        }

        return array_values(array_unique($resolved));
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

    /**
     * @param  array<int, string>  $ips
     */
    private function containsPrivateOrReservedIp(array $ips): bool
    {
        foreach ($ips as $ip) {
            if ($this->isPrivateOrReservedIp($ip)) {
                return true;
            }
        }

        return false;
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
