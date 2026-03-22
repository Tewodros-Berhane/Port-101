<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Support\Api\ApiQuery;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait InteractsWithApiResponses
{
    /**
     * @param  array<mixed>|Arrayable<mixed>|Jsonable|mixed  $data
     * @param  array<string, mixed>  $meta
     */
    protected function respond(
        mixed $data,
        int $status = 200,
        array $meta = [],
        ?string $message = null,
    ): JsonResponse {
        $payload = ['data' => $data];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  array<int, mixed>  $data
     * @param  array<string, mixed>  $filters
     */
    protected function respondPaginated(
        LengthAwarePaginator $paginator,
        array $data,
        string $sort,
        string $direction,
        array $filters = [],
    ): JsonResponse {
        return $this->respond(
            data: $data,
            meta: ApiQuery::paginationMeta($paginator, $sort, $direction, $filters),
        );
    }

    protected function respondNoContent(): JsonResponse
    {
        return response()->json(status: 204);
    }
}
