<?php

use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Projects\Models\ProjectStage;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Projects\Models\ProjectTimesheet;
use Database\Seeders\CoreRolesSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function assignProjectsWorkspaceRole(User $user, string $companyId, string $roleSlug): void
{
    $role = Role::query()
        ->where('slug', $roleSlug)
        ->whereNull('company_id')
        ->firstOrFail();

    $user->memberships()
        ->where('company_id', $companyId)
        ->update([
            'role_id' => $role->id,
            'is_owner' => false,
        ]);

    $user->forceFill([
        'current_company_id' => $companyId,
    ])->save();
}

function createProjectsWorkspaceCurrency(string $companyId, string $userId): Currency
{
    return Currency::create([
        'company_id' => $companyId,
        'code' => 'PW'.Str::upper(Str::random(1)),
        'name' => 'Projects Workspace Currency '.Str::upper(Str::random(4)),
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

/**
 * @return array{project: Project, stage: ProjectStage}
 */
function createProjectsWorkspaceProject(
    string $companyId,
    User $manager,
    Currency $currency,
    ?User $member = null
): array {
    $project = Project::create([
        'company_id' => $companyId,
        'currency_id' => $currency->id,
        'project_code' => 'PRJ-WS-'.Str::upper(Str::random(4)),
        'name' => 'Projects Workspace '.Str::upper(Str::random(4)),
        'status' => Project::STATUS_ACTIVE,
        'billing_type' => Project::BILLING_TYPE_TIME_AND_MATERIAL,
        'project_manager_id' => $manager->id,
        'start_date' => now()->toDateString(),
        'target_end_date' => now()->addWeeks(2)->toDateString(),
        'health_status' => Project::HEALTH_STATUS_ON_TRACK,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    ProjectMember::create([
        'company_id' => $companyId,
        'project_id' => $project->id,
        'user_id' => $manager->id,
        'project_role' => ProjectMember::ROLE_MANAGER,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    if ($member) {
        ProjectMember::create([
            'company_id' => $companyId,
            'project_id' => $project->id,
            'user_id' => $member->id,
            'project_role' => ProjectMember::ROLE_MEMBER,
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);
    }

    $stage = ProjectStage::create([
        'company_id' => $companyId,
        'name' => 'Execution',
        'sequence' => 1,
        'color' => 'blue',
        'is_closed_stage' => false,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    return [
        'project' => $project,
        'stage' => $stage,
    ];
}

test('project manager can access the projects workspace and create a project', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    assignProjectsWorkspaceRole($manager, $company->id, 'project_manager');
    $currency = createProjectsWorkspaceCurrency($company->id, $manager->id);

    actingAs($manager)
        ->get(route('company.modules.projects'))
        ->assertOk();

    actingAs($manager)
        ->get(route('company.projects.index'))
        ->assertOk();

    actingAs($manager)
        ->post(route('company.projects.store'), [
            'project_code' => 'PRJ-TEST-001',
            'name' => 'Implementation Rollout',
            'description' => 'Initial services delivery project.',
            'customer_id' => null,
            'sales_order_id' => null,
            'currency_id' => $currency->id,
            'status' => Project::STATUS_DRAFT,
            'billing_type' => Project::BILLING_TYPE_TIME_AND_MATERIAL,
            'project_manager_id' => $manager->id,
            'start_date' => now()->toDateString(),
            'target_end_date' => now()->addWeek()->toDateString(),
            'budget_amount' => 4500,
            'budget_hours' => 90,
            'progress_percent' => 0,
            'health_status' => Project::HEALTH_STATUS_ON_TRACK,
        ])
        ->assertRedirect();

    $project = Project::query()
        ->where('project_code', 'PRJ-TEST-001')
        ->first();

    expect($project)->not->toBeNull();
    expect($project?->project_manager_id)->toBe($manager->id);

    actingAs($manager)
        ->get(route('company.projects.show', $project))
        ->assertOk()
        ->assertSee('Implementation Rollout');
});

test('project manager can create update and delete tasks from the project workspace', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    $assignee = User::factory()->create();

    $company->users()->attach($assignee->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    assignProjectsWorkspaceRole($manager, $company->id, 'project_manager');
    assignProjectsWorkspaceRole($assignee, $company->id, 'project_user');

    $currency = createProjectsWorkspaceCurrency($company->id, $manager->id);
    $records = createProjectsWorkspaceProject($company->id, $manager, $currency);
    $project = $records['project'];

    actingAs($manager)
        ->get(route('company.projects.tasks.create', $project))
        ->assertOk();

    actingAs($manager)
        ->post(route('company.projects.tasks.store', $project), [
            'task_number' => 'TASK-001',
            'title' => 'Kickoff workshop',
            'description' => 'Run kickoff and gather requirements.',
            'stage_id' => $records['stage']->id,
            'parent_task_id' => null,
            'customer_id' => null,
            'status' => ProjectTask::STATUS_TODO,
            'priority' => ProjectTask::PRIORITY_HIGH,
            'assigned_to' => $assignee->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'estimated_hours' => 8,
            'is_billable' => true,
            'billing_status' => ProjectTask::BILLING_STATUS_NOT_READY,
        ])
        ->assertRedirect();

    $task = ProjectTask::query()->where('task_number', 'TASK-001')->first();

    expect($task)->not->toBeNull();

    actingAs($manager)
        ->put(route('company.projects.tasks.update', $task), [
            'task_number' => 'TASK-001',
            'title' => 'Kickoff workshop - updated',
            'description' => 'Run kickoff, confirm scope, and capture risks.',
            'stage_id' => $records['stage']->id,
            'parent_task_id' => null,
            'customer_id' => null,
            'status' => ProjectTask::STATUS_IN_PROGRESS,
            'priority' => ProjectTask::PRIORITY_CRITICAL,
            'assigned_to' => $assignee->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(6)->toDateString(),
            'estimated_hours' => 10,
            'is_billable' => true,
            'billing_status' => ProjectTask::BILLING_STATUS_READY,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('project_tasks', [
        'id' => $task?->id,
        'title' => 'Kickoff workshop - updated',
        'priority' => ProjectTask::PRIORITY_CRITICAL,
        'assigned_to' => $assignee->id,
    ]);

    actingAs($manager)
        ->delete(route('company.projects.tasks.destroy', $task))
        ->assertRedirect(route('company.projects.show', $project));

    $this->assertSoftDeleted('project_tasks', [
        'id' => $task?->id,
    ]);
});

test('assigned project user can view project workspace but cannot create project or team tasks', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    $projectUser = User::factory()->create();

    $company->users()->attach($projectUser->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    assignProjectsWorkspaceRole($manager, $company->id, 'project_manager');
    assignProjectsWorkspaceRole($projectUser, $company->id, 'project_user');

    $currency = createProjectsWorkspaceCurrency($company->id, $manager->id);
    $records = createProjectsWorkspaceProject(
        companyId: $company->id,
        manager: $manager,
        currency: $currency,
        member: $projectUser,
    );

    $task = ProjectTask::create([
        'company_id' => $company->id,
        'project_id' => $records['project']->id,
        'stage_id' => $records['stage']->id,
        'task_number' => 'TASK-VIEW-001',
        'title' => 'Assigned delivery task',
        'status' => ProjectTask::STATUS_IN_PROGRESS,
        'priority' => ProjectTask::PRIORITY_MEDIUM,
        'assigned_to' => $projectUser->id,
        'start_date' => now()->toDateString(),
        'due_date' => now()->addDays(3)->toDateString(),
        'estimated_hours' => 4,
        'actual_hours' => 1,
        'is_billable' => true,
        'billing_status' => ProjectTask::BILLING_STATUS_NOT_READY,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    actingAs($projectUser)
        ->get(route('company.modules.projects'))
        ->assertOk();

    actingAs($projectUser)
        ->get(route('company.projects.index'))
        ->assertOk()
        ->assertSee($records['project']->project_code);

    actingAs($projectUser)
        ->get(route('company.projects.show', $records['project']))
        ->assertOk()
        ->assertSee('Assigned delivery task');

    actingAs($projectUser)
        ->get(route('company.projects.tasks.edit', $task))
        ->assertOk();

    actingAs($projectUser)
        ->get(route('company.projects.create'))
        ->assertForbidden();

    actingAs($projectUser)
        ->get(route('company.projects.tasks.create', $records['project']))
        ->assertForbidden();
});

test('project detail page exposes billing summary and linked invoices', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    assignProjectsWorkspaceRole($manager, $company->id, 'project_manager');
    $currency = createProjectsWorkspaceCurrency($company->id, $manager->id);
    $records = createProjectsWorkspaceProject($company->id, $manager, $currency);
    $project = $records['project'];
    $project->update([
        'budget_amount' => 1000,
        'budget_hours' => 10,
        'actual_cost_amount' => 200,
    ]);
    $customer = Partner::create([
        'company_id' => $company->id,
        'code' => 'CUST-PRJ-'.Str::upper(Str::random(4)),
        'name' => 'Project Billing Customer '.Str::upper(Str::random(4)),
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    $timesheet = ProjectTimesheet::create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'task_id' => null,
        'user_id' => $manager->id,
        'work_date' => now()->toDateString(),
        'description' => 'Project rollout workshop',
        'hours' => 4,
        'is_billable' => true,
        'cost_rate' => 50,
        'bill_rate' => 100,
        'cost_amount' => 200,
        'billable_amount' => 400,
        'approval_status' => ProjectTimesheet::APPROVAL_STATUS_APPROVED,
        'approved_by' => $manager->id,
        'approved_at' => now(),
        'invoice_status' => ProjectTimesheet::INVOICE_STATUS_READY,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    $readyBillable = ProjectBillable::create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'billable_type' => ProjectBillable::TYPE_TIMESHEET,
        'source_type' => ProjectTimesheet::class,
        'source_id' => $timesheet->id,
        'customer_id' => null,
        'description' => 'Project rollout workshop',
        'quantity' => 4,
        'unit_price' => 100,
        'amount' => 400,
        'currency_id' => $currency->id,
        'status' => ProjectBillable::STATUS_READY,
        'approval_status' => ProjectBillable::APPROVAL_STATUS_NOT_REQUIRED,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    $invoice = AccountingInvoice::create([
        'company_id' => $company->id,
        'partner_id' => $customer->id,
        'sales_order_id' => null,
        'purchase_order_id' => null,
        'document_type' => AccountingInvoice::TYPE_CUSTOMER_INVOICE,
        'invoice_number' => 'INV-PRJ-001',
        'status' => AccountingInvoice::STATUS_DRAFT,
        'delivery_status' => AccountingInvoice::DELIVERY_STATUS_NOT_REQUIRED,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'currency_code' => $company->currency_code,
        'subtotal' => 900,
        'tax_total' => 0,
        'grand_total' => 900,
        'paid_total' => 0,
        'balance_due' => 900,
        'notes' => 'Projects billing test invoice',
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    $invoicedBillable = ProjectBillable::create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'billable_type' => ProjectBillable::TYPE_MANUAL,
        'source_type' => Project::class,
        'source_id' => $project->id,
        'customer_id' => null,
        'description' => 'Go-live charge',
        'quantity' => 1,
        'unit_price' => 900,
        'amount' => 900,
        'currency_id' => $currency->id,
        'status' => ProjectBillable::STATUS_INVOICED,
        'approval_status' => ProjectBillable::APPROVAL_STATUS_APPROVED,
        'invoice_id' => $invoice->id,
        'invoice_line_reference' => 'INV-PRJ-001-L01',
        'approved_by' => $manager->id,
        'approved_at' => now(),
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    actingAs($manager)
        ->get(route('company.projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/projects/show')
            ->where('summary.billables_logged', 2)
            ->where('summary.billables_ready_to_invoice', 1)
            ->where('summary.billables_ready_to_invoice_amount', 400)
            ->where('summary.billables_invoiced', 1)
            ->where('summary.billables_invoiced_amount', 900)
            ->where('profitability.logged_hours', 4)
            ->where('profitability.utilization_percent', 40)
            ->where('profitability.billable_pipeline_amount', 1300)
            ->where('profitability.ready_to_invoice_amount', 400)
            ->where('profitability.invoiced_amount', 900)
            ->where('profitability.gross_margin_amount', 1100)
            ->where('profitability.gross_margin_percent', 84.62)
            ->has('project.billables', 2)
            ->where('project.billables.0.id', $invoicedBillable->id)
            ->where('project.billables.1.id', $readyBillable->id)
            ->has('project.linked_invoices', 1)
            ->where('project.linked_invoices.0.invoice_number', 'INV-PRJ-001')
            ->where('abilities.can_create_invoice_drafts', true));
});

test('projects dashboard exposes portfolio profitability metrics', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    assignProjectsWorkspaceRole($manager, $company->id, 'project_manager');
    $currency = createProjectsWorkspaceCurrency($company->id, $manager->id);
    $records = createProjectsWorkspaceProject($company->id, $manager, $currency);
    $project = $records['project'];

    $project->update([
        'budget_amount' => 2000,
        'budget_hours' => 20,
        'actual_cost_amount' => 600,
    ]);

    $timesheet = ProjectTimesheet::create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'task_id' => null,
        'user_id' => $manager->id,
        'work_date' => now()->toDateString(),
        'description' => 'Discovery and setup',
        'hours' => 10,
        'is_billable' => true,
        'cost_rate' => 60,
        'bill_rate' => 120,
        'cost_amount' => 600,
        'billable_amount' => 1200,
        'approval_status' => ProjectTimesheet::APPROVAL_STATUS_APPROVED,
        'approved_by' => $manager->id,
        'approved_at' => now(),
        'invoice_status' => ProjectTimesheet::INVOICE_STATUS_READY,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    ProjectBillable::create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'billable_type' => ProjectBillable::TYPE_TIMESHEET,
        'source_type' => ProjectTimesheet::class,
        'source_id' => $timesheet->id,
        'customer_id' => null,
        'description' => 'Discovery and setup',
        'quantity' => 10,
        'unit_price' => 120,
        'amount' => 1200,
        'currency_id' => $currency->id,
        'status' => ProjectBillable::STATUS_READY,
        'approval_status' => ProjectBillable::APPROVAL_STATUS_NOT_REQUIRED,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    ProjectBillable::create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'billable_type' => ProjectBillable::TYPE_MANUAL,
        'source_type' => Project::class,
        'source_id' => $project->id,
        'customer_id' => null,
        'description' => 'Implementation milestone',
        'quantity' => 1,
        'unit_price' => 800,
        'amount' => 800,
        'currency_id' => $currency->id,
        'status' => ProjectBillable::STATUS_INVOICED,
        'approval_status' => ProjectBillable::APPROVAL_STATUS_APPROVED,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    actingAs($manager)
        ->get(route('company.modules.projects'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/index')
            ->where('profitability.total_budget_hours', 20)
            ->where('profitability.total_logged_hours', 10)
            ->where('profitability.utilization_percent', 50)
            ->where('profitability.billable_pipeline_amount', 2000)
            ->where('profitability.ready_to_invoice_amount', 1200)
            ->where('profitability.invoiced_amount', 800)
            ->where('profitability.gross_margin_amount', 1400)
            ->where('profitability.gross_margin_percent', 70)
            ->where('profitability.negative_margin_projects', 0)
            ->where('profitability.over_budget_hour_projects', 0)
            ->where('recentProjects.0.ready_to_invoice_amount', 1200)
            ->where('recentProjects.0.invoiced_amount', 800)
            ->where('recentProjects.0.gross_margin_percent', 70)
            ->where('recentProjects.0.utilization_percent', 50));
});
