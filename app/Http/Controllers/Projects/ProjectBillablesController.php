<?php

namespace App\Http\Controllers\Projects;

use App\Core\MasterData\Models\Partner;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectBillablesController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ProjectBillable::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $filters = $request->validate([
            'project_id' => ['nullable', 'uuid'],
            'customer_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(ProjectBillable::STATUSES)],
            'approval_status' => ['nullable', Rule::in(ProjectBillable::APPROVAL_STATUSES)],
            'billable_type' => ['nullable', Rule::in(ProjectBillable::TYPES)],
        ]);

        $projectQuery = Project::query()
            ->with('customer:id,name')
            ->accessibleTo($user);

        $billablesQuery = ProjectBillable::query()
            ->with([
                'customer:id,name',
                'project:id,project_code,name,customer_id',
                'project.customer:id,name',
                'currency:id,code,symbol',
                'invoice:id,invoice_number',
            ])
            ->whereHas('project', function (Builder $builder) use ($user): void {
                $builder->accessibleTo($user);
            });

        $filteredBillablesQuery = $this->applyFilters(
            query: $billablesQuery,
            filters: $filters,
        );

        $billables = $filteredBillablesQuery
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        $summaryQuery = $this->applyFilters(
            query: ProjectBillable::query()->whereHas(
                'project',
                fn (Builder $builder) => $builder->accessibleTo($user),
            ),
            filters: $filters,
        );

        $projects = $projectQuery
            ->orderBy('project_code')
            ->get(['id', 'project_code', 'name', 'customer_id']);

        $customerIds = $projects
            ->pluck('customer_id')
            ->filter()
            ->unique()
            ->values();

        $customers = Partner::query()
            ->whereIn('id', $customerIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('projects/billables/index', [
            'filters' => [
                'project_id' => (string) ($filters['project_id'] ?? ''),
                'customer_id' => (string) ($filters['customer_id'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'approval_status' => (string) ($filters['approval_status'] ?? ''),
                'billable_type' => (string) ($filters['billable_type'] ?? ''),
            ],
            'statuses' => ProjectBillable::STATUSES,
            'approvalStatuses' => ProjectBillable::APPROVAL_STATUSES,
            'billableTypes' => ProjectBillable::TYPES,
            'projectsFilterOptions' => $projects
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'project_code' => $project->project_code,
                    'name' => $project->name,
                    'customer_name' => $project->customer?->name,
                ])
                ->values()
                ->all(),
            'customersFilterOptions' => $customers
                ->map(fn (Partner $customer) => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                ])
                ->values()
                ->all(),
            'summary' => [
                'ready_to_invoice_count' => (clone $summaryQuery)
                    ->whereIn('status', [
                        ProjectBillable::STATUS_READY,
                        ProjectBillable::STATUS_APPROVED,
                    ])
                    ->whereNotIn('approval_status', [
                        ProjectBillable::APPROVAL_STATUS_PENDING,
                        ProjectBillable::APPROVAL_STATUS_REJECTED,
                    ])
                    ->whereNull('invoice_id')
                    ->count(),
                'pending_approval_count' => (clone $summaryQuery)
                    ->where('approval_status', ProjectBillable::APPROVAL_STATUS_PENDING)
                    ->where('status', '!=', ProjectBillable::STATUS_CANCELLED)
                    ->count(),
                'invoiced_count' => (clone $summaryQuery)
                    ->where(function (Builder $builder): void {
                        $builder
                            ->where('status', ProjectBillable::STATUS_INVOICED)
                            ->orWhereNotNull('invoice_id');
                    })
                    ->count(),
                'uninvoiced_amount' => round(
                    (float) (clone $summaryQuery)
                        ->where('status', '!=', ProjectBillable::STATUS_CANCELLED)
                        ->where(function (Builder $builder): void {
                            $builder
                                ->whereNull('invoice_id')
                                ->where('status', '!=', ProjectBillable::STATUS_INVOICED);
                        })
                        ->sum('amount'),
                    2,
                ),
            ],
            'billables' => $billables->through(fn (ProjectBillable $billable) => [
                'id' => $billable->id,
                'project_id' => $billable->project_id,
                'project_code' => $billable->project?->project_code,
                'project_name' => $billable->project?->name,
                'customer_name' => $billable->customer?->name
                    ?? $billable->project?->customer?->name,
                'billable_type' => $billable->billable_type,
                'description' => $billable->description,
                'status' => $billable->status,
                'approval_status' => $billable->approval_status,
                'quantity' => (float) $billable->quantity,
                'unit_price' => (float) $billable->unit_price,
                'amount' => (float) $billable->amount,
                'currency_code' => $billable->currency?->code,
                'invoice_number' => $billable->invoice?->invoice_number,
                'updated_at' => $billable->updated_at?->toIso8601String(),
                'can_open_project' => $billable->project !== null
                    ? $user->can('view', $billable->project)
                    : false,
            ]),
            'abilities' => [
                'can_view_projects_workspace' => $user->can('viewAny', Project::class),
            ],
        ]);
    }

    /**
     * @param  array{
     *     project_id?: string|null,
     *     customer_id?: string|null,
     *     status?: string|null,
     *     approval_status?: string|null,
     *     billable_type?: string|null
     * }  $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                filled($filters['project_id'] ?? null),
                fn (Builder $builder) => $builder->where(
                    'project_id',
                    (string) $filters['project_id'],
                ),
            )
            ->when(
                filled($filters['customer_id'] ?? null),
                fn (Builder $builder) => $builder->where(
                    'customer_id',
                    (string) $filters['customer_id'],
                ),
            )
            ->when(
                filled($filters['status'] ?? null),
                fn (Builder $builder) => $builder->where(
                    'status',
                    (string) $filters['status'],
                ),
            )
            ->when(
                filled($filters['approval_status'] ?? null),
                fn (Builder $builder) => $builder->where(
                    'approval_status',
                    (string) $filters['approval_status'],
                ),
            )
            ->when(
                filled($filters['billable_type'] ?? null),
                fn (Builder $builder) => $builder->where(
                    'billable_type',
                    (string) $filters['billable_type'],
                ),
            );
    }
}
