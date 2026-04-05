<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()'
        );

        if ($request->isSecure() && (bool) config('security.hsts.enabled', false)) {
            $response->headers->set('Strict-Transport-Security', $this->strictTransportSecurityValue());
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $nonce = Vite::cspNonce();

        $scriptSrc = ["'self'", "'nonce-{$nonce}'"];
        $styleSrc = ["'self'", "'nonce-{$nonce}'", 'https://fonts.bunny.net'];
        $connectSrc = ["'self'", 'https://fonts.bunny.net'];
        $fontSrc = ["'self'", 'data:', 'https://fonts.bunny.net'];
        $imgSrc = ["'self'", 'data:', 'blob:'];

        if ($viteOrigin = $this->viteDevOrigin()) {
            $scriptSrc[] = $viteOrigin;
            $styleSrc[] = $viteOrigin;
            $styleSrc[] = "'unsafe-inline'";
            $connectSrc[] = $viteOrigin;
            $connectSrc[] = $this->websocketOrigin($viteOrigin);
            $scriptSrc[] = "'unsafe-eval'";
        }

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "frame-src 'none'",
            "object-src 'none'",
            'script-src '.implode(' ', array_unique($scriptSrc)),
            'style-src '.implode(' ', array_unique($styleSrc)),
            'img-src '.implode(' ', array_unique($imgSrc)),
            'font-src '.implode(' ', array_unique($fontSrc)),
            'connect-src '.implode(' ', array_unique($connectSrc)),
            "manifest-src 'self'",
        ]);
    }

    private function strictTransportSecurityValue(): string
    {
        $segments = [
            'max-age='.(int) config('security.hsts.max_age', 31536000),
        ];

        if ((bool) config('security.hsts.include_subdomains', true)) {
            $segments[] = 'includeSubDomains';
        }

        if ((bool) config('security.hsts.preload', false)) {
            $segments[] = 'preload';
        }

        return implode('; ', $segments);
    }

    private function viteDevOrigin(): ?string
    {
        $hotFile = public_path('hot');

        if (! is_file($hotFile)) {
            return null;
        }

        $hotUrl = trim((string) file_get_contents($hotFile));

        if ($hotUrl === '') {
            return null;
        }

        $parts = parse_url($hotUrl);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $parts['scheme'].'://'.$this->formatHost($parts['host']).$port;
    }

    private function websocketOrigin(string $origin): string
    {
        $parts = parse_url($origin);
        $scheme = ($parts['scheme'] ?? 'http') === 'https' ? 'wss' : 'ws';
        $host = $this->formatHost($parts['host'] ?? 'localhost');
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port;
    }

    private function formatHost(string $host): string
    {
        if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
            return '['.$host.']';
        }

        return $host;
    }
}
