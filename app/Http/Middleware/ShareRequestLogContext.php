<?php

namespace App\Http\Middleware;

use App\Support\Logging\StructuredLogContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShareRequestLogContext
{
    public function __construct(
        private readonly StructuredLogContext $logContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->logContext->setRequestContext($request);

        try {
            /** @var Response $response */
            return $next($request);
        } finally {
            $this->logContext->clearScope('request');
            $this->logContext->clearScope('runtime');
        }
    }
}
