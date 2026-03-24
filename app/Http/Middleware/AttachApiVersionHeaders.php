<?php

namespace App\Http\Middleware;

use App\Support\Api\ApiVersionPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttachApiVersionHeaders
{
    public function __construct(
        private readonly ApiVersionPolicy $versionPolicy,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        return $this->versionPolicy->applyHeadersForRequest($response, $request);
    }
}
