<?php

namespace App\Support\Api;

use App\Modules\Integrations\Models\ApiIdempotencyKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class IdempotencyService
{
    public const HEADER = 'Idempotency-Key';

    private const TTL_HOURS = 24;

    /**
     * @return array{status: 'missing'|'claimed'|'replay'|'processing'|'conflict', key: string, fingerprint?: string, record?: ApiIdempotencyKey, response?: JsonResponse}
     */
    public function claim(Request $request, string $companyId, string $userId): array
    {
        $key = $this->extractKey($request);

        if ($key === '') {
            return [
                'status' => 'missing',
                'key' => '',
            ];
        }

        $fingerprint = $this->fingerprint($request);

        while (true) {
            $record = ApiIdempotencyKey::query()
                ->where('company_id', $companyId)
                ->where('user_id', $userId)
                ->where('key', $key)
                ->first();

            if ($record !== null && $record->expires_at !== null && $record->expires_at->isPast()) {
                $record->delete();
                $record = null;
            }

            if ($record instanceof ApiIdempotencyKey) {
                if (! hash_equals((string) $record->request_fingerprint, $fingerprint)) {
                    return [
                        'status' => 'conflict',
                        'key' => $key,
                        'fingerprint' => $fingerprint,
                        'record' => $record,
                    ];
                }

                if ($record->response_status !== null) {
                    return [
                        'status' => 'replay',
                        'key' => $key,
                        'fingerprint' => $fingerprint,
                        'record' => $record,
                        'response' => $this->replayResponse($record),
                    ];
                }

                return [
                    'status' => 'processing',
                    'key' => $key,
                    'fingerprint' => $fingerprint,
                    'record' => $record,
                ];
            }

            try {
                $created = ApiIdempotencyKey::create([
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'key' => $key,
                    'request_fingerprint' => $fingerprint,
                    'expires_at' => now()->addHours(self::TTL_HOURS),
                ]);

                return [
                    'status' => 'claimed',
                    'key' => $key,
                    'fingerprint' => $fingerprint,
                    'record' => $created,
                ];
            } catch (QueryException $exception) {
                if ($this->wasUniqueConstraintViolation($exception)) {
                    continue;
                }

                throw $exception;
            }
        }
    }

    public function finalize(ApiIdempotencyKey $record, Request $request, JsonResponse $response): void
    {
        if (! $this->shouldPersistResponse($response)) {
            $record->delete();

            return;
        }

        $payload = $response->getData(true);

        if (! is_array($payload)) {
            $payload = ['data' => $payload];
        }

        $record->forceFill([
            'response_status' => $response->getStatusCode(),
            'response_body' => $payload,
            'resource_type' => $this->resourceType($request),
            'resource_id' => $this->resourceId($payload),
            'expires_at' => now()->addHours(self::TTL_HOURS),
        ])->save();
    }

    public function release(ApiIdempotencyKey $record): void
    {
        $record->delete();
    }

    public function decorateResponse(JsonResponse $response, string $key, bool $replayed = false): JsonResponse
    {
        $response->headers->set(self::HEADER, $key);
        $response->headers->set('X-Port101-Idempotency-Replayed', $replayed ? 'true' : 'false');

        return $response;
    }

    private function replayResponse(ApiIdempotencyKey $record): JsonResponse
    {
        $status = (int) ($record->response_status ?? 200);

        $response = $status === 204
            ? response()->json(status: 204)
            : response()->json($record->response_body ?? [], $status);

        return $this->decorateResponse($response, (string) $record->key, true);
    }

    private function extractKey(Request $request): string
    {
        return trim((string) $request->header(self::HEADER), " \t\n\r\0\x0B\"'");
    }

    private function fingerprint(Request $request): string
    {
        $routeParameters = collect($request->route()?->parametersWithoutNulls() ?? [])
            ->map(fn (mixed $value) => $value instanceof Model ? $value->getKey() : $value)
            ->all();

        $payload = [
            'method' => strtoupper($request->getMethod()),
            'path' => $request->path(),
            'route_parameters' => $this->normalize($routeParameters),
            'payload' => $this->normalize($request->all()),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function shouldPersistResponse(JsonResponse $response): bool
    {
        $status = $response->getStatusCode();

        return $status >= 200 && $status < 300;
    }

    private function resourceType(Request $request): ?string
    {
        $action = $request->route()?->getActionName();

        return is_string($action) && $action !== 'Closure' ? $action : null;
    }

    private function resourceId(array $payload): ?string
    {
        $candidates = [
            Arr::get($payload, 'data.id'),
            Arr::get($payload, 'data.order.id'),
            Arr::get($payload, 'data.quote.id'),
            Arr::get($payload, 'data.delivery.id'),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                return (string) $candidate;
            }
        }

        return null;
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalize($item);
        }

        if ($this->isAssociative($normalized)) {
            ksort($normalized);
        }

        return $normalized;
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    private function isAssociative(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    private function wasUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return $sqlState === '23505';
    }
}
