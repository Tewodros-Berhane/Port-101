<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Projects\ProjectStoreRequest;
use App\Http\Requests\Projects\ProjectUpdateRequest;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\ProjectWorkspaceService;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectsController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Project::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $perPage = ApiQuery::perPage($request);
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $billingType = trim((string) $request->input('billing_type', ''));
        $externalReference = trim((string) $request->input('external_reference', ''));
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['updated_at', 'name', 'project_code', 'external_reference', 'status', 'start_date'],
            defaultSort: 'updated_at',
            defaultDirection: 'desc',
        );

        $projects = Project::query()
            ->with(['customer:id,name', 'salesOrder:id,order_number', 'currency:id,code', 'projectManager:id,name'])
            ->withCount(['members', 'tasks', 'timesheets', 'milestones', 'billables'])
            ->accessibleTo($user)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('project_code', 'like', "%{$search}%")
                        ->orWhere('external_reference', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($billingType !== '', fn ($query) => $query->where('billing_type', $billingType))
            ->when($externalReference !== '', fn ($query) => $query->where('external_reference', $externalReference))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $projects,
            data: collect($projects->items())
                ->map(fn (Project $project) => $this->mapProject($project, $user, false))
                ->all(),
            sort: $sort,
            direction: $direction,
            filters: [
                'search' => $search,
                'status' => $status,
                'billing_type' => $billingType,
                'external_reference' => $externalReference,
            ],
        );
    }

    public function store(
        ProjectStoreRequest $request,
        ProjectWorkspaceService $workspaceService
    ): JsonResponse {
        $this->authorize('create', Project::class);

        $user = $request->user();

        $project = Project::create([
            ...$request->validated(),
            'company_id' => $user?->current_company_id,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        $workspaceService->ensureDefaultStages(
            companyId: (string) $project->company_id,
            actorId: $user?->id,
        );
        $workspaceService->syncProjectMembers($project, $user?->id);
        $workspaceService->refreshProjectRollup($project);

        return $this->respond(
            $this->mapProject(
                $project->fresh([
                    'customer:id,name',
                    'salesOrder:id,order_number',
                    'currency:id,code',
                    'projectManager:id,name',
                    'members.user:id,name,email',
                ]),
                $user,
            ),
            201,
        );
    }

    public function show(Project $project, Request $request): JsonResponse
    {
        $this->authorize('view', $project);

        $project->load([
            'customer:id,name',
            'salesOrder:id,order_number',
            'currency:id,code',
            'projectManager:id,name,email',
            'members.user:id,name,email',
        ])->loadCount(['members', 'tasks', 'timesheets', 'milestones', 'billables']);

        return $this->respond($this->mapProject($project, $request->user(), true));
    }

    public function update(
        ProjectUpdateRequest $request,
        Project $project,
        ProjectWorkspaceService $workspaceService
    ): JsonResponse {
        $this->authorize('update', $project);

        $project->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        $workspaceService->syncProjectMembers($project, $request->user()?->id);
        $workspaceService->refreshProjectRollup($project);

        return $this->respond(
            $this->mapProject(
                $project->fresh([
                    'customer:id,name',
                    'salesOrder:id,order_number',
                    'currency:id,code',
                    'projectManager:id,name,email',
                    'members.user:id,name,email',
                ])->loadCount(['members', 'tasks', 'timesheets', 'milestones', 'billables']),
                $request->user(),
                true,
            ),
        );
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $project->members()->delete();
        $project->tasks()->delete();
        $project->timesheets()->delete();
        $project->milestones()->delete();
        $project->billables()->delete();
        $project->delete();

        return $this->respondNoContent();
    }

    private function mapProject(
        ?Project $project,
        ?User $user = null,
        bool $includeMembers = true
    ): array {
        $project ??= new Project;

        $payload = [
            'id' => $project->id,
            'external_reference' => $project->external_reference,
            'project_code' => $project->project_code,
            'name' => $project->name,
            'description' => $project->description,
            'status' => $project->status,
            'billing_type' => $project->billing_type,
            'health_status' => $project->health_status,
            'customer_id' => $project->customer_id,
            'customer_name' => $project->customer?->name,
            'sales_order_id' => $project->sales_order_id,
            'sales_order_number' => $project->salesOrder?->order_number,
            'currency_id' => $project->currency_id,
            'currency_code' => $project->currency?->code,
            'project_manager_id' => $project->project_manager_id,
            'project_manager_name' => $project->projectManager?->name,
            'project_manager_email' => $project->projectManager?->email,
            'start_date' => $project->start_date?->toDateString(),
            'target_end_date' => $project->target_end_date?->toDateString(),
            'completed_at' => $project->completed_at?->toIso8601String(),
            'budget_amount' => $project->budget_amount !== null ? (float) $project->budget_amount : null,
            'budget_hours' => $project->budget_hours !== null ? (float) $project->budget_hours : null,
            'actual_cost_amount' => (float) ($project->actual_cost_amount ?? 0),
            'actual_billable_amount' => (float) ($project->actual_billable_amount ?? 0),
            'progress_percent' => (float) ($project->progress_percent ?? 0),
            'team_members_count' => (int) ($project->members_count ?? $project->members()->count()),
            'tasks_count' => (int) ($project->tasks_count ?? $project->tasks()->count()),
            'timesheets_count' => (int) ($project->timesheets_count ?? $project->timesheets()->count()),
            'milestones_count' => (int) ($project->milestones_count ?? $project->milestones()->count()),
            'billables_count' => (int) ($project->billables_count ?? $project->billables()->count()),
            'updated_at' => $project->updated_at?->toIso8601String(),
            'can_view' => $user?->can('view', $project) ?? false,
            'can_edit' => $user?->can('update', $project) ?? false,
        ];

        if (! $includeMembers) {
            return $payload;
        }

        $payload['members'] = $project->relationLoaded('members')
            ? $project->members
                ->map(fn ($member) => [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
                    'name' => $member->user?->name,
                    'email' => $member->user?->email,
                    'project_role' => $member->project_role,
                    'allocation_percent' => $member->allocation_percent !== null
                        ? (float) $member->allocation_percent
                        : null,
                ])
                ->values()
                ->all()
            : [];

        return $payload;
    }
}
