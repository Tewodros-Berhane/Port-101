<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Api\ApiErrorResponse;
use App\Support\Api\IdempotencyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireIdempotency
{
    public function __construct(
        private readonly IdempotencyService $idempotencyService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $companyId = (string) $user?->current_company_id;

        if (! $user instanceof User || $companyId === '') {
            return ApiErrorResponse::make('Company context not available.', 403);
        }

        $claim = $this->idempotencyService->claim($request, $companyId, $user->id);

        return match ($claim['status']) {
            'missing' => ApiErrorResponse::make(
                'Idempotency-Key header is required for this endpoint.',
                400,
                meta: ['header' => IdempotencyService::HEADER],
            ),
            'conflict' => ApiErrorResponse::make(
                'Idempotency-Key cannot be reused with a different request payload.',
                422,
                meta: ['header' => IdempotencyService::HEADER],
            ),
            'processing' => ApiErrorResponse::make(
                'A request with this Idempotency-Key is already being processed.',
                409,
                meta: ['header' => IdempotencyService::HEADER],
            ),
            'replay' => $claim['response'],
            'claimed' => $this->handleClaimed($request, $next, $claim['record'], $claim['key']),
        };
    }

    private function handleClaimed(
        Request $request,
        Closure $next,
        mixed $record,
        string $key,
    ): Response {
        try {
            $response = $next($request);
        } catch (\Throwable $throwable) {
            if ($record !== null) {
                $this->idempotencyService->release($record);
            }

            throw $throwable;
        }

        if ($record !== null && $response instanceof \Illuminate\Http\JsonResponse) {
            $this->idempotencyService->finalize($record, $request, $response);

            return $this->idempotencyService->decorateResponse($response, $key);
        }

        if ($record !== null) {
            $this->idempotencyService->release($record);
        }

        return $response;
    }
}
