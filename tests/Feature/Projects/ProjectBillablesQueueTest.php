<?php

use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Projects\Models\ProjectTimesheet;
use App\Modules\Projects\ProjectBillingService;
use Database\Seeders\CoreRolesSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function projectsBillablesAssignRole(User $user, string $companyId, string $roleSlug): void
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

function projectsBillablesCurrency(string $companyId, string $userId): Currency
{
    return Currency::create([
        'company_id' => $companyId,
        'code' => 'QB'.Str::upper(Str::random(1)),
        'name' => 'Project Queue Currency '.Str::upper(Str::random(4)),
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

function projectsBillablesCustomer(string $companyId, string $userId, string $name): Partner
{
    return Partner::create([
        'company_id' => $companyId,
        'code' => 'CUST-Q-'.Str::upper(Str::random(4)),
        'name' => $name,
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

function projectsBillablesProject(
    string $companyId,
    User $manager,
    Currency $currency,
    Partner $customer,
    string $codeSuffix,
): Project {
    $project = Project::create([
        'company_id' => $companyId,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'project_code' => 'PRJ-BQ-'.$codeSuffix,
        'name' => 'Billing Queue '.$codeSuffix,
        'status' => Project::STATUS_ACTIVE,
        'billing_type' => Project::BILLING_TYPE_TIME_AND_MATERIAL,
        'project_manager_id' => $manager->id,
        'start_date' => now()->toDateString(),
        'target_end_date' => now()->addWeeks(3)->toDateString(),
        'health_status' => Project::HEALTH_STATUS_ON_TRACK,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    ProjectMember::create([
        'company_id' => $companyId,
        'project_id' => $project->id,
        'user_id' => $manager->id,
        'project_role' => ProjectMember::ROLE_MANAGER,
        'hourly_cost_rate' => 50,
        'hourly_bill_rate' => 120,
        'is_billable_by_default' => true,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    return $project;
}

function projectsBillablesApprovedTimesheet(
    Project $project,
    User $owner,
    string $description,
    float $hours,
    float $billRate,
): ProjectTimesheet {
    $task = ProjectTask::create([
        'company_id' => $project->company_id,
        'project_id' => $project->id,
        'task_number' => 'TASK-BQ-'.Str::upper(Str::random(4)),
        'title' => 'Billable task '.Str::upper(Str::random(3)),
        'status' => ProjectTask::STATUS_IN_PROGRESS,
        'priority' => ProjectTask::PRIORITY_HIGH,
        'assigned_to' => $owner->id,
        'start_date' => now()->toDateString(),
        'estimated_hours' => 8,
        'actual_hours' => $hours,
        'is_billable' => true,
        'billing_status' => ProjectTask::BILLING_STATUS_READY,
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    return ProjectTimesheet::create([
        'company_id' => $project->company_id,
        'project_id' => $project->id,
        'task_id' => $task->id,
        'user_id' => $owner->id,
        'work_date' => now()->toDateString(),
        'description' => $description,
        'hours' => $hours,
        'is_billable' => true,
        'cost_rate' => 50,
        'bill_rate' => $billRate,
        'cost_amount' => round($hours * 50, 2),
        'billable_amount' => round($hours * $billRate, 2),
        'approval_status' => ProjectTimesheet::APPROVAL_STATUS_APPROVED,
        'approved_by' => $owner->id,
        'approved_at' => now(),
        'invoice_status' => ProjectTimesheet::INVOICE_STATUS_READY,
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);
}

function projectsBillablesApprovedMilestone(
    Project $project,
    User $owner,
    string $name,
    float $amount,
): ProjectMilestone {
    return ProjectMilestone::create([
        'company_id' => $project->company_id,
        'project_id' => $project->id,
        'name' => $name,
        'sequence' => 1,
        'status' => ProjectMilestone::STATUS_APPROVED,
        'due_date' => now()->addDays(10)->toDateString(),
        'completed_at' => now(),
        'approved_by' => $owner->id,
        'approved_at' => now(),
        'amount' => $amount,
        'invoice_status' => ProjectMilestone::INVOICE_STATUS_READY,
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);
}

test('project manager can view the billables queue with filters and summary metrics', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    projectsBillablesAssignRole($manager, $company->id, 'project_manager');

    $currency = projectsBillablesCurrency($company->id, $manager->id);
    $customerA = projectsBillablesCustomer($company->id, $manager->id, 'Acme Logistics');
    $customerB = projectsBillablesCustomer($company->id, $manager->id, 'Northwind Advisory');
    $projectA = projectsBillablesProject($company->id, $manager, $currency, $customerA, 'A001');
    $projectB = projectsBillablesProject($company->id, $manager, $currency, $customerB, 'B001');

    $billingService = app(ProjectBillingService::class);

    $timesheet = projectsBillablesApprovedTimesheet(
        project: $projectA,
        owner: $manager,
        description: 'Implementation workshop',
        hours: 3,
        billRate: 120,
    );
    $readyBillable = $billingService->syncFromTimesheet($timesheet, $manager->id);

    $milestone = projectsBillablesApprovedMilestone(
        project: $projectA,
        owner: $manager,
        name: 'Kickoff approval',
        amount: 1200,
    );
    $pendingBillable = $billingService->syncFromMilestone($milestone, $manager->id);
    $pendingBillable?->update([
        'approval_status' => ProjectBillable::APPROVAL_STATUS_PENDING,
        'updated_by' => $manager->id,
    ]);

    $invoicedBillable = ProjectBillable::create([
        'company_id' => $company->id,
        'project_id' => $projectB->id,
        'billable_type' => ProjectBillable::TYPE_MANUAL,
        'source_type' => Project::class,
        'source_id' => $projectB->id,
        'customer_id' => $customerB->id,
        'description' => 'Prior invoiced onboarding package',
        'quantity' => 1,
        'unit_price' => 500,
        'amount' => 500,
        'currency_id' => $currency->id,
        'status' => ProjectBillable::STATUS_INVOICED,
        'approval_status' => ProjectBillable::APPROVAL_STATUS_APPROVED,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    actingAs($manager)
        ->get(route('company.projects.billables.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/billables/index')
            ->where('summary.ready_to_invoice_count', 1)
            ->where('summary.pending_approval_count', 1)
            ->where('summary.invoiced_count', 1)
            ->where('summary.uninvoiced_amount', 1560)
            ->has('projectsFilterOptions', 2)
            ->has('customersFilterOptions', 2)
            ->has('billables.data', 3));

    actingAs($manager)
        ->get(route('company.projects.billables.index', [
            'project_id' => $projectA->id,
            'approval_status' => ProjectBillable::APPROVAL_STATUS_PENDING,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/billables/index')
            ->where('filters.project_id', $projectA->id)
            ->where('filters.approval_status', ProjectBillable::APPROVAL_STATUS_PENDING)
            ->where('summary.ready_to_invoice_count', 0)
            ->where('summary.pending_approval_count', 1)
            ->where('summary.invoiced_count', 0)
            ->where('summary.uninvoiced_amount', 1200)
            ->has('billables.data', 1)
            ->where('billables.data.0.id', $pendingBillable?->id)
            ->where('billables.data.0.project_id', $projectA->id));

    expect($readyBillable)->not->toBeNull();
    expect($invoicedBillable)->not->toBeNull();
});

test('finance manager can view the billables queue without project membership', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    $financeManager = User::factory()->create();

    $company->users()->attach($financeManager->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    projectsBillablesAssignRole($manager, $company->id, 'project_manager');
    projectsBillablesAssignRole($financeManager, $company->id, 'finance_manager');

    $currency = projectsBillablesCurrency($company->id, $manager->id);
    $customer = projectsBillablesCustomer($company->id, $manager->id, 'Finance Visibility Co');
    $project = projectsBillablesProject($company->id, $manager, $currency, $customer, 'F001');

    $timesheet = projectsBillablesApprovedTimesheet(
        project: $project,
        owner: $manager,
        description: 'Finance-visible billable work',
        hours: 2,
        billRate: 150,
    );

    $billable = app(ProjectBillingService::class)->syncFromTimesheet(
        $timesheet,
        $manager->id,
    );

    actingAs($financeManager)
        ->get(route('company.projects.billables.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/billables/index')
            ->has('billables.data', 1)
            ->where('billables.data.0.id', $billable?->id)
            ->where('billables.data.0.project_id', $project->id));
});

test('project user without billables permission cannot access the billables queue', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    $projectUser = User::factory()->create();

    $company->users()->attach($projectUser->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    projectsBillablesAssignRole($manager, $company->id, 'project_manager');
    projectsBillablesAssignRole($projectUser, $company->id, 'project_user');

    actingAs($projectUser)
        ->get(route('company.projects.billables.index'))
        ->assertForbidden();
});
