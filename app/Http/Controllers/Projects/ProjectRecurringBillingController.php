<?php

namespace App\Http\Controllers\Projects;

use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectRecurringBillingStoreRequest;
use App\Http\Requests\Projects\ProjectRecurringBillingUpdateRequest;
use App\Models\User;
use App\Modules\Approvals\ApprovalQueueService;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectRecurringBilling;
use App\Modules\Projects\Models\ProjectRecurringBillingRun;
use App\Modules\Projects\ProjectInvoiceDraftService;
use App\Modules\Projects\ProjectRecurringBillingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectRecurringBillingController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ProjectRecurringBilling::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $filters = $request->validate([
            'project_id' => ['nullable', 'uuid'],
            'customer_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(ProjectRecurringBilling::STATUSES)],
            'frequency' => ['nullable', Rule::in(ProjectRecurringBilling::FREQUENCIES)],
            'auto_invoice' => ['nullable', Rule::in(['yes', 'no'])],
        ]);

        $scheduleQuery = ProjectRecurringBilling::query()
            ->with([
                'project:id,project_code,name',
                'customer:id,name',
                'currency:id,code,symbol',
                'lastInvoice:id,invoice_number',
                'runs' => fn ($builder) => $builder
                    ->latest('scheduled_for')
                    ->latest('created_at')
                    ->limit(1),
            ])
            ->accessibleTo($user);

        $filteredQuery = $this->applyFilters($scheduleQuery, $filters);
        $schedules = $filteredQuery
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        $summaryRows = $this->applyFilters(
            ProjectRecurringBilling::query()->accessibleTo($user),
            $filters,
        )->get([
            'id',
            'customer_id',
            'status',
            'quantity',
            'unit_price',
            'next_run_on',
            'auto_create_invoice_draft',
        ]);

        $projects = Project::query()
            ->accessibleTo($user)
            ->with('customer:id,name')
            ->orderBy('project_code')
            ->get(['id', 'project_code', 'name', 'customer_id']);

        $customerIds = $projects
            ->pluck('customer_id')
            ->merge($summaryRows->pluck('customer_id'))
            ->filter()
            ->unique()
            ->values();

        $customers = Partner::query()
            ->whereIn('id', $customerIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $today = now()->toDateString();

        return Inertia::render('projects/recurring-billing/index', [
            'filters' => [
                'project_id' => (string) ($filters['project_id'] ?? ''),
                'customer_id' => (string) ($filters['customer_id'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'frequency' => (string) ($filters['frequency'] ?? ''),
                'auto_invoice' => (string) ($filters['auto_invoice'] ?? ''),
            ],
            'statuses' => ProjectRecurringBilling::STATUSES,
            'frequencies' => ProjectRecurringBilling::FREQUENCIES,
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
                'active_count' => $summaryRows
                    ->where('status', ProjectRecurringBilling::STATUS_ACTIVE)
                    ->count(),
                'due_now_count' => $summaryRows
                    ->where('status', ProjectRecurringBilling::STATUS_ACTIVE)
                    ->filter(fn (ProjectRecurringBilling $schedule) => $schedule->next_run_on !== null
                        && $schedule->next_run_on->toDateString() <= $today)
                    ->count(),
                'auto_invoice_count' => $summaryRows
                    ->where('auto_create_invoice_draft', true)
                    ->count(),
                'active_recurring_amount' => round(
                    (float) $summaryRows
                        ->where('status', ProjectRecurringBilling::STATUS_ACTIVE)
                        ->sum(fn (ProjectRecurringBilling $schedule) => (float) $schedule->quantity * (float) $schedule->unit_price),
                    2,
                ),
            ],
            'schedules' => $schedules->through(function (ProjectRecurringBilling $schedule) use ($user) {
                $latestRun = $schedule->runs->first();
                $amount = round((float) $schedule->quantity * (float) $schedule->unit_price, 2);

                return [
                    'id' => $schedule->id,
                    'project_id' => $schedule->project_id,
                    'project_code' => $schedule->project?->project_code,
                    'project_name' => $schedule->project?->name,
                    'customer_name' => $schedule->customer?->name,
                    'name' => $schedule->name,
                    'description' => $schedule->description,
                    'frequency' => $schedule->frequency,
                    'quantity' => (float) $schedule->quantity,
                    'unit_price' => (float) $schedule->unit_price,
                    'amount' => $amount,
                    'currency_code' => $schedule->currency?->code,
                    'status' => $schedule->status,
                    'next_run_on' => $schedule->next_run_on?->toDateString(),
                    'ends_on' => $schedule->ends_on?->toDateString(),
                    'auto_create_invoice_draft' => (bool) $schedule->auto_create_invoice_draft,
                    'invoice_grouping' => $schedule->invoice_grouping,
                    'last_run_at' => $schedule->last_run_at?->toIso8601String(),
                    'last_invoice_id' => $schedule->last_invoice_id,
                    'last_invoice_number' => $schedule->lastInvoice?->invoice_number,
                    'can_open_last_invoice' => $schedule->lastInvoice !== null
                        ? $user->can('view', $schedule->lastInvoice)
                        : false,
                    'latest_run_status' => $latestRun?->status,
                    'latest_cycle_label' => $latestRun?->cycle_label,
                    'latest_error_message' => $latestRun?->error_message,
                    'latest_invoice_id' => $latestRun?->invoice_id,
                    'latest_invoice_number' => $latestRun?->invoice?->invoice_number,
                    'can_edit' => $user->can('update', $schedule),
                    'can_run' => $user->can('run', $schedule)
                        && $schedule->status === ProjectRecurringBilling::STATUS_ACTIVE,
                    'can_activate' => $user->can('update', $schedule)
                        && in_array($schedule->status, [
                            ProjectRecurringBilling::STATUS_DRAFT,
                            ProjectRecurringBilling::STATUS_PAUSED,
                        ], true),
                    'can_pause' => $user->can('update', $schedule)
                        && $schedule->status === ProjectRecurringBilling::STATUS_ACTIVE,
                    'can_cancel' => $user->can('update', $schedule)
                        && ! in_array($schedule->status, [
                            ProjectRecurringBilling::STATUS_CANCELLED,
                            ProjectRecurringBilling::STATUS_COMPLETED,
                        ], true),
                    'can_open_project' => $schedule->project !== null
                        ? $user->can('view', $schedule->project)
                        : false,
                ];
            }),
            'abilities' => [
                'can_create' => $user->can('create', ProjectRecurringBilling::class),
                'can_view_projects_workspace' => $user->can('viewAny', Project::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', ProjectRecurringBilling::class);

        $selectedProject = $this->selectedManageableProject($request);
        $projectOptions = $this->manageableProjectOptions($request);
        $currencies = $this->currencyOptions();
        $customers = $this->customerOptions();
        $defaultStatus = ProjectRecurringBilling::STATUS_DRAFT;
        $defaultStartDate = now()->toDateString();

        return Inertia::render('projects/recurring-billing/create', [
            'recurringBilling' => [
                'project_id' => $selectedProject?->id ?? ($projectOptions[0]['id'] ?? ''),
                'customer_id' => $selectedProject?->customer_id ?? '',
                'currency_id' => $selectedProject?->currency_id ?? ($currencies[0]['id'] ?? ''),
                'name' => '',
                'description' => '',
                'frequency' => ProjectRecurringBilling::FREQUENCY_MONTHLY,
                'quantity' => '1',
                'unit_price' => '',
                'invoice_due_days' => '30',
                'starts_on' => $defaultStartDate,
                'next_run_on' => $defaultStartDate,
                'ends_on' => '',
                'auto_create_invoice_draft' => false,
                'invoice_grouping' => ProjectInvoiceDraftService::GROUP_BY_PROJECT,
                'status' => $defaultStatus,
            ],
            'projects' => $projectOptions,
            'customers' => $customers,
            'currencies' => $currencies,
            'frequencies' => ProjectRecurringBilling::FREQUENCIES,
            'statuses' => [
                ProjectRecurringBilling::STATUS_DRAFT,
                ProjectRecurringBilling::STATUS_ACTIVE,
            ],
            'invoiceGroupingOptions' => ProjectInvoiceDraftService::GROUP_BY_OPTIONS,
        ]);
    }

    public function store(
        ProjectRecurringBillingStoreRequest $request,
        ProjectRecurringBillingService $service,
    ): RedirectResponse {
        $this->authorize('create', ProjectRecurringBilling::class);

        $project = $this->manageableProjectOrFail(
            request: $request,
            projectId: (string) $request->validated('project_id'),
        );

        $service->create(
            project: $project,
            attributes: $request->validated(),
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.projects.recurring-billing.index')
            ->with('success', 'Recurring billing schedule created.');
    }

    public function edit(ProjectRecurringBilling $recurringBilling): Response
    {
        $this->authorize('update', $recurringBilling);

        return Inertia::render('projects/recurring-billing/edit', [
            'recurringBilling' => [
                'id' => $recurringBilling->id,
                'project_id' => $recurringBilling->project_id,
                'project_code' => $recurringBilling->project?->project_code,
                'project_name' => $recurringBilling->project?->name,
                'customer_id' => $recurringBilling->customer_id,
                'currency_id' => $recurringBilling->currency_id,
                'name' => $recurringBilling->name,
                'description' => $recurringBilling->description,
                'frequency' => $recurringBilling->frequency,
                'quantity' => (string) $recurringBilling->quantity,
                'unit_price' => (string) $recurringBilling->unit_price,
                'invoice_due_days' => (string) $recurringBilling->invoice_due_days,
                'starts_on' => $recurringBilling->starts_on?->toDateString(),
                'next_run_on' => $recurringBilling->next_run_on?->toDateString(),
                'ends_on' => $recurringBilling->ends_on?->toDateString(),
                'auto_create_invoice_draft' => (bool) $recurringBilling->auto_create_invoice_draft,
                'invoice_grouping' => $recurringBilling->invoice_grouping,
                'status' => $recurringBilling->status,
            ],
            'customers' => $this->customerOptions(),
            'currencies' => $this->currencyOptions(),
            'frequencies' => ProjectRecurringBilling::FREQUENCIES,
            'statuses' => [
                ProjectRecurringBilling::STATUS_DRAFT,
                ProjectRecurringBilling::STATUS_ACTIVE,
                ProjectRecurringBilling::STATUS_PAUSED,
            ],
            'invoiceGroupingOptions' => ProjectInvoiceDraftService::GROUP_BY_OPTIONS,
        ]);
    }

    public function update(
        ProjectRecurringBillingUpdateRequest $request,
        ProjectRecurringBilling $recurringBilling,
        ProjectRecurringBillingService $service,
    ): RedirectResponse {
        $this->authorize('update', $recurringBilling);

        $service->update(
            schedule: $recurringBilling,
            attributes: $request->validated(),
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.projects.recurring-billing.edit', $recurringBilling)
            ->with('success', 'Recurring billing schedule updated.');
    }

    public function activate(
        Request $request,
        ProjectRecurringBilling $recurringBilling,
        ProjectRecurringBillingService $service,
    ): RedirectResponse {
        $this->authorize('update', $recurringBilling);

        $service->activate($recurringBilling, $request->user()?->id);

        return back()->with('success', 'Recurring billing schedule activated.');
    }

    public function pause(
        Request $request,
        ProjectRecurringBilling $recurringBilling,
        ProjectRecurringBillingService $service,
    ): RedirectResponse {
        $this->authorize('update', $recurringBilling);

        $service->pause($recurringBilling, $request->user()?->id);

        return back()->with('success', 'Recurring billing schedule paused.');
    }

    public function cancel(
        Request $request,
        ProjectRecurringBilling $recurringBilling,
        ProjectRecurringBillingService $service,
    ): RedirectResponse {
        $this->authorize('update', $recurringBilling);

        $payload = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->cancel(
            schedule: $recurringBilling,
            reason: $payload['reason'] ?? null,
            actorId: $request->user()?->id,
        );

        return back()->with('success', 'Recurring billing schedule cancelled.');
    }

    public function runNow(
        Request $request,
        ProjectRecurringBilling $recurringBilling,
        ProjectRecurringBillingService $service,
        ApprovalQueueService $approvalQueueService,
    ): RedirectResponse {
        $this->authorize('run', $recurringBilling);

        try {
            $run = $service->runNow($recurringBilling, $request->user()?->id);
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->with('error', collect($exception->errors())->flatten()->first()
                    ?? 'Recurring billing run failed.');
        }

        if ($company = $request->user()?->currentCompany) {
            $approvalQueueService->syncPendingRequests($company, $request->user()?->id);
        }

        if (! $run) {
            return back()->with('error', 'Recurring billing schedule did not generate a run.');
        }

        if ($run->status === ProjectRecurringBillingRun::STATUS_FAILED) {
            return back()->with('error', $run->error_message ?: 'Recurring billing run failed.');
        }

        return back()->with('success', 'Recurring billing run processed.');
    }

    /**
     * @param  array{
     *     project_id?: string|null,
     *     customer_id?: string|null,
     *     status?: string|null,
     *     frequency?: string|null,
     *     auto_invoice?: string|null
     * }  $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                filled($filters['project_id'] ?? null),
                fn (Builder $builder) => $builder->where('project_id', (string) $filters['project_id']),
            )
            ->when(
                filled($filters['customer_id'] ?? null),
                fn (Builder $builder) => $builder->where('customer_id', (string) $filters['customer_id']),
            )
            ->when(
                filled($filters['status'] ?? null),
                fn (Builder $builder) => $builder->where('status', (string) $filters['status']),
            )
            ->when(
                filled($filters['frequency'] ?? null),
                fn (Builder $builder) => $builder->where('frequency', (string) $filters['frequency']),
            )
            ->when(
                filled($filters['auto_invoice'] ?? null),
                fn (Builder $builder) => $builder->where(
                    'auto_create_invoice_draft',
                    (string) $filters['auto_invoice'] === 'yes',
                ),
            );
    }

    /**
     * @return array<int, array{id: string, project_code: string, name: string, customer_id: string|null, customer_name: string|null, currency_id: string|null, can_manage: bool}>
     */
    private function manageableProjectOptions(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return [];
        }

        return Project::query()
            ->with('customer:id,name')
            ->accessibleTo($user)
            ->orderBy('project_code')
            ->get(['id', 'project_code', 'name', 'customer_id', 'currency_id'])
            ->filter(fn (Project $project) => $user->can('update', $project))
            ->values()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'project_code' => $project->project_code,
                'name' => $project->name,
                'customer_id' => $project->customer_id,
                'customer_name' => $project->customer?->name,
                'currency_id' => $project->currency_id,
                'can_manage' => true,
            ])
            ->all();
    }

    private function selectedManageableProject(Request $request): ?Project
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $projectId = trim((string) $request->query('project_id', ''));

        if ($projectId === '') {
            return null;
        }

        $project = Project::query()
            ->accessibleTo($user)
            ->with(['customer:id,name'])
            ->find($projectId);

        if (! $project || ! $user->can('update', $project)) {
            return null;
        }

        return $project;
    }

    private function manageableProjectOrFail(Request $request, string $projectId): Project
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $project = Project::query()
            ->accessibleTo($user)
            ->findOrFail($projectId);

        $this->authorize('update', $project);

        return $project;
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
}
