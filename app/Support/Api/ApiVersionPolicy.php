<?php

namespace App\Support\Api;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiVersionPolicy
{
    public function detectVersion(Request $request): ?string
    {
        if ($request->is('api/v1') || $request->is('api/v1/*')) {
            return 'v1';
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public function headersForVersion(string $version): array
    {
        $config = $this->configForVersion($version);

        if ($config === []) {
            return [];
        }

        $headers = [
            'X-API-Version' => $version,
        ];

        $deprecationAt = $config['deprecation_at'] ?? null;

        if (is_string($deprecationAt) && trim($deprecationAt) !== '') {
            $headers['Deprecation'] = '@'.CarbonImmutable::parse($deprecationAt)->timestamp;
        }

        $sunsetAt = $config['sunset_at'] ?? null;

        if (is_string($sunsetAt) && trim($sunsetAt) !== '') {
            $headers['Sunset'] = CarbonImmutable::parse($sunsetAt)->toRfc7231String();
        }

        return $headers;
    }

    /**
     * @return array<string, mixed>
     */
    public function metaForVersion(string $version): array
    {
        $config = $this->configForVersion($version);

        return [
            'version' => $version,
            'status' => $config['status'] ?? 'stable',
            'deprecation_at' => $this->normalizeIso8601($config['deprecation_at'] ?? null),
            'sunset_at' => $this->normalizeIso8601($config['sunset_at'] ?? null),
            'change_policy' => $config['change_policy'] ?? [],
            'changelog_categories' => $config['changelog_categories'] ?? [],
        ];
    }

    public function applyHeaders(Response $response, string $version): Response
    {
        foreach ($this->headersForVersion($version) as $header => $value) {
            $response->headers->set($header, $value);
        }

        return $response;
    }

    public function applyHeadersForRequest(Response $response, Request $request): Response
    {
        $version = $this->detectVersion($request);

        if ($version === null) {
            return $response;
        }

        return $this->applyHeaders($response, $version);
    }

    /**
     * @return array<string, mixed>
     */
    private function configForVersion(string $version): array
    {
        $config = config("api_versioning.versions.{$version}");

        return is_array($config) ? $config : [];
    }

    private function normalizeIso8601(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->toIso8601String();
    }
}
