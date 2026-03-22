<?php

namespace App\Support\Api;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiQuery
{
    public static function perPage(Request $request, int $default = 20, int $max = 100): int
    {
        $perPage = $request->integer('per_page', $default);

        return max(1, min($perPage, $max));
    }

    /**
     * @param  array<int, string>  $allowed
     * @return array{sort: string, direction: 'asc'|'desc'}
     */
    public static function sort(
        Request $request,
        array $allowed,
        string $defaultSort,
        string $defaultDirection = 'desc',
    ): array {
        $sort = (string) $request->input('sort', $defaultSort);
        $direction = strtolower((string) $request->input('direction', $defaultDirection));

        if (! in_array($sort, $allowed, true)) {
            $sort = $defaultSort;
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = $defaultDirection;
        }

        return [
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    /**
     * @param  EloquentBuilder<*>|QueryBuilder  $query
     */
    public static function applySort(EloquentBuilder|QueryBuilder $query, string $sort, string $direction): void
    {
        $query->orderBy($sort, $direction);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function filters(array $filters): array
    {
        return collect($filters)
            ->reject(fn (mixed $value): bool => $value === null || $value === '')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function paginationMeta(
        LengthAwarePaginator $paginator,
        string $sort,
        string $direction,
        array $filters = [],
    ): array {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'sort' => $sort,
            'direction' => $direction,
            'filters' => self::filters($filters),
        ];
    }
}
