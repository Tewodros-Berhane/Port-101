<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\MasterData\Models\Partner;
use App\Http\Requests\Core\PartnerStoreRequest;
use App\Http\Requests\Core\PartnerUpdateRequest;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnersController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Partner::class);

        $perPage = ApiQuery::perPage($request);
        $search = trim((string) $request->input('search', ''));
        $externalReference = trim((string) $request->input('external_reference', ''));
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['name', 'code', 'external_reference', 'created_at', 'updated_at'],
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        $partners = Partner::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('external_reference', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($externalReference !== '', fn ($query) => $query->where('external_reference', $externalReference))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $partners,
            data: $partners->items(),
            sort: $sort,
            direction: $direction,
            filters: [
                'search' => $search,
                'external_reference' => $externalReference,
            ],
        );
    }

    public function store(PartnerStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Partner::class);

        $user = $request->user();

        $partner = Partner::create([
            ...$request->validated(),
            'company_id' => $user?->current_company_id,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        return $this->respond($partner, 201);
    }

    public function show(Partner $partner): JsonResponse
    {
        $this->authorize('view', $partner);

        return $this->respond($partner);
    }

    public function update(PartnerUpdateRequest $request, Partner $partner): JsonResponse
    {
        $this->authorize('update', $partner);

        $partner->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return $this->respond($partner->fresh());
    }

    public function destroy(Partner $partner): JsonResponse
    {
        $this->authorize('delete', $partner);

        $partner->delete();

        return $this->respondNoContent();
    }
}
