<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Sales\SalesLeadStoreRequest;
use App\Http\Requests\Sales\SalesLeadUpdateRequest;
use App\Models\User;
use App\Modules\Sales\Models\SalesLead;
use App\Modules\Sales\SalesLeadWorkflowService;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesLeadsController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SalesLead::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $perPage = ApiQuery::perPage($request);
        $search = trim((string) $request->input('search', ''));
        $stage = trim((string) $request->input('stage', ''));
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['created_at', 'title', 'stage', 'expected_close_date', 'estimated_value', 'updated_at'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        $leads = SalesLead::query()
            ->with(['partner:id,name'])
            ->withCount('quotes')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('title', 'like', "%{$search}%")
                        ->orWhereHas('partner', fn ($partnerQuery) => $partnerQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($stage !== '', fn ($query) => $query->where('stage', $stage))
            ->tap(fn ($query) => $user->applyDataScopeToQuery($query))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $leads,
            data: collect($leads->items())
                ->map(fn (SalesLead $lead) => $this->mapLead($lead, $user))
                ->all(),
            sort: $sort,
            direction: $direction,
            filters: [
                'search' => $search,
                'stage' => $stage,
            ],
        );
    }

    public function store(
        SalesLeadStoreRequest $request,
        SalesLeadWorkflowService $workflowService
    ): JsonResponse {
        $this->authorize('create', SalesLead::class);

        $lead = $workflowService->create($request->validated(), $request->user());

        return $this->respond(
            $this->mapLead($lead->load('partner:id,name'), $request->user()),
            201,
        );
    }

    public function show(SalesLead $lead, Request $request): JsonResponse
    {
        $this->authorize('view', $lead);

        $lead->load('partner:id,name')->loadCount('quotes');

        return $this->respond($this->mapLead($lead, $request->user()));
    }

    public function update(
        SalesLeadUpdateRequest $request,
        SalesLead $lead,
        SalesLeadWorkflowService $workflowService
    ): JsonResponse {
        $this->authorize('update', $lead);

        $lead = $workflowService->update($lead, $request->validated(), $request->user());

        return $this->respond(
            $this->mapLead($lead->load('partner:id,name')->loadCount('quotes'), $request->user()),
        );
    }

    public function destroy(
        SalesLead $lead,
        SalesLeadWorkflowService $workflowService
    ): JsonResponse {
        $this->authorize('delete', $lead);

        $workflowService->delete($lead);

        return $this->respondNoContent();
    }

    private function mapLead(SalesLead $lead, ?User $user = null): array
    {
        return [
            'id' => $lead->id,
            'partner_id' => $lead->partner_id,
            'partner_name' => $lead->partner?->name,
            'title' => $lead->title,
            'stage' => $lead->stage,
            'estimated_value' => $lead->estimated_value !== null ? (float) $lead->estimated_value : null,
            'expected_close_date' => $lead->expected_close_date?->toDateString(),
            'notes' => $lead->notes,
            'converted_at' => $lead->converted_at?->toIso8601String(),
            'quotes_count' => (int) ($lead->quotes_count ?? $lead->quotes()->count()),
            'updated_at' => $lead->updated_at?->toIso8601String(),
            'can_view' => $user?->can('view', $lead) ?? false,
            'can_edit' => $user?->can('update', $lead) ?? false,
            'can_delete' => $user?->can('delete', $lead) ?? false,
        ];
    }
}
