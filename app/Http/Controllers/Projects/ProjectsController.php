<?php

namespace App\Http\Controllers\Projects;

use App\Core\Company\Models\CompanyUser;
use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectStoreRequest;
use App\Http\Requests\Projects\ProjectUpdateRequest;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Modules\Projects\Models\ProjectRecurringBilling;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Projects\Models\ProjectTimesheet;
use App\Modules\Projects\ProjectInvoiceDraftService;
use App\Modules\Projects\ProjectProfitabilityService;
use App\Modules\Projects\ProjectWorkspaceService;
use App\Modules\Sales\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Project::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
            'billing_type' => ['nullable', 'string'],
        ]);

        $projects = Project::query()
            ->with(['customer:id,name', 'projectManager:id,name'])
            ->withCount('tasks')
            ->accessibleTo($user)
            ->when(
                filled($filters['search'] ?? null),
                function ($builder) use ($filters): void {
                    $search = trim((string) ($filters['search'] ?? ''));

                    $builder->where(function ($nested) use ($search): void {
                        $nested
                            ->where('project_code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
                },
            )
            ->when(
                filled($filters['status'] ?? null),
                fn ($builder) => $builder->where(
                    'status',
                    (string) $filters['status'],
                ),
            )
            ->when(
                filled($filters['billing_type'] ?? null),
                fn ($builder) => $builder->where(
                    'billing_type',
                    (string) $filters['billing_type'],
                ),
            )
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('projects/projects/index', [
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'billing_type' => (string) ($filters['billing_type'] ?? ''),
            ],
            'statuses' => Project::STATUSES,
            'billingTypes' => Project::BILLING_TYPES,
            'projects' => $projects->through(fn (Project $project) => [
                'id' => $project->id,
                'project_code' => $project->project_code,
                'name' => $project->name,
                'status' => $project->status,
                'billing_type' => $project->billing_type,
                'health_status' => $project->health_status,
                'customer_name' => $project->customer?->name,
                'project_manager_name' => $project->projectManager?->name,
                'progress_percent' => (float) $project->progress_percent,
                'target_end_date' => $project->target_end_date?->toDateString(),
                'budget_amount' => $project->budget_amount !== null
                    ? (float) $project->budget_amount
                    : null,
                'tasks_count' => (int) $project->tasks_count,
                'can_view' => $user->can('view', $project),
                'can_edit' => $user->can('update', $project),
            ]),
            'abilities' => [
                'can_create_project' => $user->can('create', Project::class),
                'can_view_billables' => $user->can('viewAny', ProjectBillable::class),
                'can_view_recurring' => $user->can('viewAny', ProjectRecurringBilling::class),
            ],
        ]);
    }

    public function create(
        Request $request,
        ProjectWorkspaceService $workspaceService
    ): Response {
        $this->authorize('create', Project::class);

        [$currencies, $projectManagers, $customers, $salesOrders] = [
            $this->currencyOptions(),
            $this->projectManagerOptions($request),
            $this->customerOptions(),
            $this->salesOrderOptions(),
        ];

        $workspaceService->ensureDefaultStages(
            companyId: (string) $request->user()?->current_company_id,
            actorId: $request->user()?->id,
        );

        return Inertia::render('projects/projects/create', [
            'project' => [
                'project_code' => '',
                'name' => '',
                'description' => '',
                'customer_id' => '',
                'sales_order_id' => '',
                'currency_id' => $currencies[0]['id'] ?? '',
                'status' => Project::STATUS_DRAFT,
                'billing_type' => Project::BILLING_TYPE_TIME_AND_MATERIAL,
                'project_manager_id' => $projectManagers[0]['id'] ?? '',
                'start_date' => now()->toDateString(),
                'target_end_date' => '',
                'budget_amount' => '',
                'budget_hours' => '',
                'progress_percent' => '0',
                'health_status' => Project::HEALTH_STATUS_ON_TRACK,
            ],
            'customers' => $customers,
            'salesOrders' => $salesOrders,
            'currencies' => $currencies,
            'projectManagers' => $projectManagers,
            'statuses' => Project::STATUSES,
            'billingTypes' => Project::BILLING_TYPES,
            'healthStatuses' => Project::HEALTH_STATUSES,
        ]);
    }

    public function store(
        ProjectStoreRequest $request,
        ProjectWorkspaceService $workspaceService
    ): RedirectResponse {
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

        return redirect()
            ->route('company.projects.show', $project)
            ->with('success', 'Project created.');
    }

    public function show(
        Project $project,
        Request $request,
        ProjectProfitabilityService $profitabilityService,
    ): Response {
        $this->authorize('view', $project);

        $user = $request->user();

        $project->load([
            'customer:id,name',
            'salesOrder:id,order_number,status',
            'currency:id,code,name,symbol',
            'projectManager:id,name,email',
            'members.user:id,name,email',
            'tasks.stage:id,name,color',
            'tasks.assignee:id,name',
            'timesheets.user:id,name',
            'timesheets.task:id,task_number,title',
            'milestones.approvedBy:id,name',
            'billables.currency:id,code,symbol',
            'billables.invoice:id,invoice_number,status,invoice_date,due_date,grand_total,balance_due',
            'recurringBillings.customer:id,name',
            'recurringBillings.currency:id,code,symbol',
            'recurringBillings.lastInvoice:id,invoice_number',
            'recurringBillings.runs' => fn ($builder) => $builder
                ->latest('scheduled_for')
                ->latest('created_at')
                ->limit(1),
        ]);

        $tasks = $project->tasks
            ->sortBy([
                ['due_date', 'asc'],
                ['created_at', 'desc'],
            ])
            ->values();
        $timesheets = $project->timesheets
            ->sortByDesc('work_date')
            ->values();
        $milestones = $project->milestones
            ->sortBy('sequence')
            ->values();
        $billables = $project->billables
            ->sortByDesc('updated_at')
            ->values();
        $recurringBillings = $project->recurringBillings
            ->sortBy([
                ['status', 'asc'],
                ['next_run_on', 'asc'],
            ])
            ->values();
        $linkedInvoices = $billables
            ->map(fn (ProjectBillable $billable) => $billable->invoice)
            ->filter()
            ->unique(fn ($invoice) => (string) $invoice->id)
            ->sortByDesc('invoice_date')
            ->values();

        $overdueTaskCount = $tasks
            ->whereNotIn('status', [
                ProjectTask::STATUS_DONE,
                ProjectTask::STATUS_CANCELLED,
            ])
            ->filter(fn (ProjectTask $task) => $task->due_date !== null
                && $task->due_date->isPast()
            )
            ->count();
        $profitability = $profitabilityService->summarizeProject($project);

        return Inertia::render('projects/projects/show', [
            'project' => [
                'id' => $project->id,
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
                'currency_code' => $project->currency?->code,
                'project_manager_name' => $project->projectManager?->name,
                'project_manager_email' => $project->projectManager?->email,
                'start_date' => $project->start_date?->toDateString(),
                'target_end_date' => $project->target_end_date?->toDateString(),
                'completed_at' => $project->completed_at?->toIso8601String(),
                'budget_amount' => $project->budget_amount !== null
                    ? (float) $project->budget_amount
                    : null,
                'budget_hours' => $project->budget_hours !== null
                    ? (float) $project->budget_hours
                    : null,
                'actual_cost_amount' => (float) $project->actual_cost_amount,
                'actual_billable_amount' => (float) $project->actual_billable_amount,
                'progress_percent' => (float) $project->progress_percent,
                'members' => $project->members
                    ->sortBy(function (ProjectMember $member): string {
                        return sprintf(
                            '%s-%s',
                            $member->project_role === ProjectMember::ROLE_MANAGER ? '0' : '1',
                            strtolower((string) $member->user?->name),
                        );
                    })
                    ->map(fn (ProjectMember $member) => [
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
                    ->all(),
                'tasks' => $tasks
                    ->map(fn (ProjectTask $task) => [
                        'id' => $task->id,
                        'task_number' => $task->task_number,
                        'title' => $task->title,
                        'status' => $task->status,
                        'priority' => $task->priority,
                        'stage_name' => $task->stage?->name,
                        'assignee_name' => $task->assignee?->name,
                        'due_date' => $task->due_date?->toDateString(),
                        'estimated_hours' => $task->estimated_hours !== null
                            ? (float) $task->estimated_hours
                            : null,
                        'actual_hours' => (float) $task->actual_hours,
                        'can_edit' => $user?->can('update', $task) ?? false,
                    ])
                    ->all(),
                'timesheets' => $timesheets
                    ->map(fn (ProjectTimesheet $timesheet) => [
                        'id' => $timesheet->id,
                        'user_name' => $timesheet->user?->name,
                        'task_number' => $timesheet->task?->task_number,
                        'task_title' => $timesheet->task?->title,
                        'work_date' => $timesheet->work_date?->toDateString(),
                        'hours' => (float) $timesheet->hours,
                        'is_billable' => (bool) $timesheet->is_billable,
                        'cost_amount' => (float) $timesheet->cost_amount,
                        'billable_amount' => (float) $timesheet->billable_amount,
                        'approval_status' => $timesheet->approval_status,
                        'invoice_status' => $timesheet->invoice_status,
                        'rejection_reason' => $timesheet->rejection_reason,
                        'can_edit' => $user?->can('update', $timesheet) ?? false,
                        'can_submit' => $user?->can('submit', $timesheet) ?? false,
                        'can_approve' => $user?->can('approve', $timesheet) ?? false,
                        'can_reject' => $user?->can('reject', $timesheet) ?? false,
                    ])
                    ->all(),
                'milestones' => $milestones
                    ->map(fn (ProjectMilestone $milestone) => [
                        'id' => $milestone->id,
                        'name' => $milestone->name,
                        'sequence' => (int) $milestone->sequence,
                        'status' => $milestone->status,
                        'due_date' => $milestone->due_date?->toDateString(),
                        'completed_at' => $milestone->completed_at?->toIso8601String(),
                        'amount' => (float) $milestone->amount,
                        'invoice_status' => $milestone->invoice_status,
                        'approved_by_name' => $milestone->approvedBy?->name,
                        'approved_at' => $milestone->approved_at?->toIso8601String(),
                        'can_edit' => $user?->can('update', $milestone) ?? false,
                    ])
                    ->all(),
                'billables' => $billables
                    ->map(fn (ProjectBillable $billable) => [
                        'id' => $billable->id,
                        'billable_type' => $billable->billable_type,
                        'description' => $billable->description,
                        'status' => $billable->status,
                        'approval_status' => $billable->approval_status,
                        'quantity' => (float) $billable->quantity,
                        'unit_price' => (float) $billable->unit_price,
                        'amount' => (float) $billable->amount,
                        'currency_code' => $billable->currency?->code,
                        'invoice_id' => $billable->invoice_id,
                        'invoice_number' => $billable->invoice?->invoice_number,
                        'invoice_status' => $billable->invoice?->status,
                        'updated_at' => $billable->updated_at?->toIso8601String(),
                        'can_create_invoice' => $user?->can('createInvoice', $billable)
                            && in_array($billable->status, [
                                ProjectBillable::STATUS_READY,
                                ProjectBillable::STATUS_APPROVED,
                            ], true)
                            && ! in_array($billable->approval_status, [
                                ProjectBillable::APPROVAL_STATUS_PENDING,
                                ProjectBillable::APPROVAL_STATUS_REJECTED,
                            ], true)
                            && $billable->status !== ProjectBillable::STATUS_CANCELLED
                            && ! $billable->invoice_id,
                        'can_open_invoice' => $billable->invoice !== null
                            ? ($user?->can('view', $billable->invoice) ?? false)
                            : false,
                    ])
                    ->all(),
                'linked_invoices' => $linkedInvoices
                    ->map(fn ($invoice) => [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'status' => $invoice->status,
                        'invoice_date' => $invoice->invoice_date?->toDateString(),
                        'due_date' => $invoice->due_date?->toDateString(),
                        'grand_total' => (float) $invoice->grand_total,
                        'balance_due' => (float) $invoice->balance_due,
                        'can_open' => $user?->can('view', $invoice) ?? false,
                    ])
                    ->all(),
                'recurring_billings' => $recurringBillings
                    ->map(function (ProjectRecurringBilling $schedule) use ($user) {
                        $latestRun = $schedule->runs->first();

                        return [
                            'id' => $schedule->id,
                            'name' => $schedule->name,
                            'description' => $schedule->description,
                            'customer_name' => $schedule->customer?->name,
                            'frequency' => $schedule->frequency,
                            'quantity' => (float) $schedule->quantity,
                            'unit_price' => (float) $schedule->unit_price,
                            'amount' => round((float) $schedule->quantity * (float) $schedule->unit_price, 2),
                            'currency_code' => $schedule->currency?->code,
                            'status' => $schedule->status,
                            'next_run_on' => $schedule->next_run_on?->toDateString(),
                            'ends_on' => $schedule->ends_on?->toDateString(),
                            'auto_create_invoice_draft' => (bool) $schedule->auto_create_invoice_draft,
                            'invoice_grouping' => $schedule->invoice_grouping,
                            'last_invoice_id' => $schedule->last_invoice_id,
                            'last_invoice_number' => $schedule->lastInvoice?->invoice_number,
                            'latest_run_status' => $latestRun?->status,
                            'latest_cycle_label' => $latestRun?->cycle_label,
                            'latest_error_message' => $latestRun?->error_message,
                            'can_edit' => $user?->can('update', $schedule) ?? false,
                            'can_run' => $user?->can('run', $schedule) ?? false,
                        ];
                    })
                    ->all(),
            ],
            'summary' => [
                'task_total' => $tasks->count(),
                'task_completed' => $tasks
                    ->where('status', ProjectTask::STATUS_DONE)
                    ->count(),
                'task_overdue' => $overdueTaskCount,
                'team_members' => $project->members->count(),
                'timesheets_logged' => ProjectTimesheet::query()
                    ->where('project_id', $project->id)
                    ->count(),
                'timesheets_pending_approval' => ProjectTimesheet::query()
                    ->where('project_id', $project->id)
                    ->where('approval_status', ProjectTimesheet::APPROVAL_STATUS_SUBMITTED)
                    ->count(),
                'milestones_total' => $milestones->count(),
                'milestones_ready_review' => $milestones
                    ->where('status', ProjectMilestone::STATUS_READY_FOR_REVIEW)
                    ->count(),
                'billables_logged' => ProjectBillable::query()
                    ->where('project_id', $project->id)
                    ->where('status', '!=', ProjectBillable::STATUS_CANCELLED)
                    ->count(),
                'billables_ready_to_invoice' => $billables
                    ->filter(fn (ProjectBillable $billable) => in_array($billable->status, [
                        ProjectBillable::STATUS_READY,
                        ProjectBillable::STATUS_APPROVED,
                    ], true)
                        && ! in_array($billable->approval_status, [
                            ProjectBillable::APPROVAL_STATUS_PENDING,
                            ProjectBillable::APPROVAL_STATUS_REJECTED,
                        ], true)
                        && $billable->status !== ProjectBillable::STATUS_CANCELLED
                        && ! $billable->invoice_id)
                    ->count(),
                'billables_ready_to_invoice_amount' => round(
                    (float) $billables
                        ->filter(fn (ProjectBillable $billable) => in_array($billable->status, [
                            ProjectBillable::STATUS_READY,
                            ProjectBillable::STATUS_APPROVED,
                        ], true)
                            && ! in_array($billable->approval_status, [
                                ProjectBillable::APPROVAL_STATUS_PENDING,
                                ProjectBillable::APPROVAL_STATUS_REJECTED,
                            ], true)
                            && $billable->status !== ProjectBillable::STATUS_CANCELLED
                            && ! $billable->invoice_id)
                        ->sum('amount'),
                    2,
                ),
                'billables_pending_approval' => $billables
                    ->where('approval_status', ProjectBillable::APPROVAL_STATUS_PENDING)
                    ->where('status', '!=', ProjectBillable::STATUS_CANCELLED)
                    ->count(),
                'billables_pending_approval_amount' => round(
                    (float) $billables
                        ->filter(fn (ProjectBillable $billable) => $billable->approval_status === ProjectBillable::APPROVAL_STATUS_PENDING
                            && $billable->status !== ProjectBillable::STATUS_CANCELLED)
                        ->sum('amount'),
                    2,
                ),
                'billables_invoiced' => $billables
                    ->filter(fn (ProjectBillable $billable) => $billable->invoice_id
                        || $billable->status === ProjectBillable::STATUS_INVOICED)
                    ->count(),
                'billables_invoiced_amount' => round(
                    (float) $billables
                        ->filter(fn (ProjectBillable $billable) => $billable->invoice_id
                            || $billable->status === ProjectBillable::STATUS_INVOICED)
                        ->sum('amount'),
                    2,
                ),
                'recurring_billing_total' => $recurringBillings->count(),
                'recurring_billing_active' => $recurringBillings
                    ->where('status', ProjectRecurringBilling::STATUS_ACTIVE)
                    ->count(),
                'recurring_billing_due_now' => $recurringBillings
                    ->filter(fn (ProjectRecurringBilling $schedule) => $schedule->status === ProjectRecurringBilling::STATUS_ACTIVE
                        && $schedule->next_run_on !== null
                        && $schedule->next_run_on->toDateString() <= now()->toDateString())
                    ->count(),
                'recurring_billing_amount' => round(
                    (float) $recurringBillings
                        ->where('status', ProjectRecurringBilling::STATUS_ACTIVE)
                        ->sum(fn (ProjectRecurringBilling $schedule) => (float) $schedule->quantity * (float) $schedule->unit_price),
                    2,
                ),
            ],
            'profitability' => $profitability,
            'abilities' => [
                'can_edit_project' => $user?->can('update', $project) ?? false,
                'can_create_task' => $user?->can('update', $project) ?? false,
                'can_create_timesheet' => $user?->can('create', ProjectTimesheet::class) ?? false,
                'can_create_milestone' => $user?->can('create', ProjectMilestone::class)
                    && ($user?->can('update', $project) ?? false),
                'can_view_billables' => $user?->can('viewAny', ProjectBillable::class) ?? false,
                'can_view_recurring' => $user?->can('viewAny', ProjectRecurringBilling::class) ?? false,
                'can_manage_recurring' => $user?->can('update', $project) ?? false
                    && ($user?->can('create', ProjectRecurringBilling::class) ?? false),
                'can_create_invoice_drafts' => $user?->hasPermission('projects.invoices.create') ?? false,
                'invoice_grouping_options' => ProjectInvoiceDraftService::GROUP_BY_OPTIONS,
            ],
        ]);
    }

    public function edit(
        Project $project,
        Request $request,
        ProjectWorkspaceService $workspaceService
    ): Response {
        $this->authorize('update', $project);

        $workspaceService->ensureDefaultStages(
            companyId: (string) $project->company_id,
            actorId: $request->user()?->id,
        );

        return Inertia::render('projects/projects/edit', [
            'project' => [
                'id' => $project->id,
                'project_code' => $project->project_code,
                'name' => $project->name,
                'description' => $project->description,
                'customer_id' => $project->customer_id,
                'sales_order_id' => $project->sales_order_id,
                'currency_id' => $project->currency_id,
                'status' => $project->status,
                'billing_type' => $project->billing_type,
                'project_manager_id' => $project->project_manager_id,
                'start_date' => $project->start_date?->toDateString(),
                'target_end_date' => $project->target_end_date?->toDateString(),
                'budget_amount' => $project->budget_amount !== null
                    ? (string) $project->budget_amount
                    : '',
                'budget_hours' => $project->budget_hours !== null
                    ? (string) $project->budget_hours
                    : '',
                'progress_percent' => (string) $project->progress_percent,
                'health_status' => $project->health_status,
            ],
            'customers' => $this->customerOptions(),
            'salesOrders' => $this->salesOrderOptions(),
            'currencies' => $this->currencyOptions(),
            'projectManagers' => $this->projectManagerOptions($request),
            'statuses' => Project::STATUSES,
            'billingTypes' => Project::BILLING_TYPES,
            'healthStatuses' => Project::HEALTH_STATUSES,
        ]);
    }

    public function update(
        ProjectUpdateRequest $request,
        Project $project,
        ProjectWorkspaceService $workspaceService
    ): RedirectResponse {
        $this->authorize('update', $project);

        $project->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        $workspaceService->syncProjectMembers($project, $request->user()?->id);
        $workspaceService->refreshProjectRollup($project);

        return redirect()
            ->route('company.projects.edit', $project)
            ->with('success', 'Project updated.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->members()->delete();
        $project->tasks()->delete();
        $project->timesheets()->delete();
        $project->milestones()->delete();
        $project->billables()->delete();
        $project->delete();

        return redirect()
            ->route('company.projects.index')
            ->with('success', 'Project removed.');
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function customerOptions(): array
    {
        return Partner::query()
            ->whereIn('type', ['customer', 'both'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Partner $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, code: string, name: string}>
     */
    private function currencyOptions(): array
    {
        return Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Currency $currency) => [
                'id' => $currency->id,
                'code' => $currency->code,
                'name' => $currency->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, order_number: string, partner_name: string|null, status: string}>
     */
    private function salesOrderOptions(): array
    {
        return SalesOrder::query()
            ->with('partner:id,name')
            ->latest('order_date')
            ->limit(50)
            ->get(['id', 'partner_id', 'order_number', 'status'])
            ->map(fn (SalesOrder $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'partner_name' => $order->partner?->name,
                'status' => $order->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, email: string, role_name: string|null}>
     */
    private function projectManagerOptions(Request $request): array
    {
        $companyId = (string) $request->user()?->current_company_id;

        if ($companyId === '') {
            return [];
        }

        return CompanyUser::query()
            ->with(['user:id,name,email', 'role:id,name'])
            ->where('company_id', $companyId)
            ->get()
            ->filter(fn (CompanyUser $membership) => $membership->user !== null)
            ->sortBy(fn (CompanyUser $membership) => strtolower((string) $membership->user?->name))
            ->values()
            ->map(fn (CompanyUser $membership) => [
                'id' => (string) $membership->user_id,
                'name' => (string) $membership->user?->name,
                'email' => (string) $membership->user?->email,
                'role_name' => $membership->is_owner
                    ? 'Owner'
                    : $membership->role?->name,
            ])
            ->all();
    }
}
