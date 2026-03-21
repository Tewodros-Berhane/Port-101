<?php

use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Modules\Projects\Models\ProjectStage;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Projects\Models\ProjectTimesheet;
use App\Modules\Sales\Models\SalesOrder;
use Database\Seeders\CoreRolesSeeder;
use Illuminate\Support\Str;

function assignProjectsCompanyRole(User $user, string $companyId, string $roleSlug): void
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

/**
 * @return array{
 *     project: Project,
 *     task: ProjectTask,
 *     timesheet: ProjectTimesheet,
 *     milestone: ProjectMilestone,
 *     billable: ProjectBillable
 * }
 */
function makeProjectsAuthorizationScenario(
    string $companyId,
    User $creator,
    User $projectManager,
    User $taskAssignee,
    string $timesheetApprovalStatus = ProjectTimesheet::APPROVAL_STATUS_DRAFT,
    bool $attachAssigneeToProject = true
): array {
    $customer = Partner::create([
        'company_id' => $companyId,
        'code' => 'CUST-'.Str::upper(Str::random(4)),
        'name' => 'Authorization Customer '.Str::upper(Str::random(4)),
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);

    $currency = Currency::create([
        'company_id' => $companyId,
        'code' => 'US'.Str::upper(Str::random(1)),
        'name' => 'Project Currency '.Str::upper(Str::random(4)),
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);

    $order = SalesOrder::create([
        'company_id' => $companyId,
        'quote_id' => null,
        'partner_id' => $customer->id,
        'order_number' => 'SO-PRJ-'.Str::upper(Str::random(4)),
        'status' => SalesOrder::STATUS_CONFIRMED,
        'order_date' => now()->toDateString(),
        'subtotal' => 2000,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 2000,
        'requires_approval' => false,
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);

    $project = Project::create([
        'company_id' => $companyId,
        'customer_id' => $customer->id,
        'sales_order_id' => $order->id,
        'currency_id' => $currency->id,
        'project_code' => 'PRJ-'.Str::upper(Str::random(4)),
        'name' => 'Delivery Project '.Str::upper(Str::random(4)),
        'status' => Project::STATUS_ACTIVE,
        'billing_type' => Project::BILLING_TYPE_TIME_AND_MATERIAL,
        'project_manager_id' => $projectManager->id,
        'start_date' => now()->toDateString(),
        'budget_amount' => 8000,
        'budget_hours' => 160,
        'health_status' => Project::HEALTH_STATUS_ON_TRACK,
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);

    ProjectMember::create([
        'company_id' => $companyId,
        'project_id' => $project->id,
        'user_id' => $projectManager->id,
        'project_role' => ProjectMember::ROLE_MANAGER,
        'allocation_percent' => 100,
        'hourly_cost_rate' => 60,
        'hourly_bill_rate' => 120,
        'is_billable_by_default' => true,
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);

    if ($attachAssigneeToProject) {
        ProjectMember::create([
            'company_id' => $companyId,
            'project_id' => $project->id,
            'user_id' => $taskAssignee->id,
            'project_role' => ProjectMember::ROLE_MEMBER,
            'allocation_percent' => 50,
            'hourly_cost_rate' => 40,
            'hourly_bill_rate' => 90,
            'is_billable_by_default' => true,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);
    }

    $stage = ProjectStage::create([
        'company_id' => $companyId,
        'name' => 'Execution',
        'sequence' => 1,
        'color' => 'blue',
        'is_closed_stage' => false,
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);

    $task = ProjectTask::create([
        'company_id' => $companyId,
        'project_id' => $project->id,
        'stage_id' => $stage->id,
        'customer_id' => $customer->id,
        'task_number' => 'TSK-'.Str::upper(Str::random(4)),
        'title' => 'Service delivery task',
        'status' => ProjectTask::STATUS_IN_PROGRESS,
        'priority' => ProjectTask::PRIORITY_HIGH,
        'assigned_to' => $taskAssignee->id,
        'start_date' => now()->toDateString(),
        'estimated_hours' => 6,
        'actual_hours' => 2,
        'is_billable' => true,
        'billing_status' => ProjectTask::BILLING_STATUS_NOT_READY,
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);

    $timesheet = ProjectTimesheet::create([
        'company_id' => $companyId,
        'project_id' => $project->id,
        'task_id' => $task->id,
        'user_id' => $taskAssignee->id,
        'work_date' => now()->toDateString(),
        'description' => 'Delivery work',
        'hours' => 2,
        'is_billable' => true,
        'cost_rate' => 40,
        'bill_rate' => 90,
        'cost_amount' => 80,
        'billable_amount' => 180,
        'approval_status' => $timesheetApprovalStatus,
        'invoice_status' => ProjectTimesheet::INVOICE_STATUS_READY,
        'created_by' => $taskAssignee->id,
        'updated_by' => $taskAssignee->id,
    ]);

    $milestone = ProjectMilestone::create([
        'company_id' => $companyId,
        'project_id' => $project->id,
        'name' => 'Milestone 1',
        'sequence' => 1,
        'status' => ProjectMilestone::STATUS_IN_PROGRESS,
        'amount' => 1500,
        'invoice_status' => ProjectMilestone::INVOICE_STATUS_NOT_READY,
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);

    $billable = ProjectBillable::create([
        'company_id' => $companyId,
        'project_id' => $project->id,
        'billable_type' => ProjectBillable::TYPE_TIMESHEET,
        'source_type' => ProjectTimesheet::class,
        'source_id' => $timesheet->id,
        'customer_id' => $customer->id,
        'description' => 'Approved billable work',
        'quantity' => 2,
        'unit_price' => 90,
        'amount' => 180,
        'currency_id' => $currency->id,
        'status' => ProjectBillable::STATUS_READY,
        'approval_status' => ProjectBillable::APPROVAL_STATUS_PENDING,
        'created_by' => $taskAssignee->id,
        'updated_by' => $taskAssignee->id,
    ]);

    return [
        'project' => $project,
        'task' => $task,
        'timesheet' => $timesheet,
        'milestone' => $milestone,
        'billable' => $billable,
    ];
}

test('core roles seeder adds project permissions and role bundles', function () {
    $this->seed(CoreRolesSeeder::class);

    expect(Permission::query()->where('slug', 'projects.projects.view')->exists())->toBeTrue();
    expect(Permission::query()->where('slug', 'projects.timesheets.approve')->exists())->toBeTrue();
    expect(Permission::query()->where('slug', 'projects.invoices.create')->exists())->toBeTrue();

    $projectManager = Role::query()->where('slug', 'project_manager')->firstOrFail();
    $projectUser = Role::query()->where('slug', 'project_user')->firstOrFail();
    $financeManager = Role::query()->where('slug', 'finance_manager')->firstOrFail();
    $approver = Role::query()->where('slug', 'approver')->firstOrFail();

    expect($projectManager->permissions->pluck('slug')->contains('projects.projects.manage'))->toBeTrue();
    expect($projectManager->permissions->pluck('slug')->contains('projects.billables.approve'))->toBeTrue();
    expect($projectUser->permissions->pluck('slug')->contains('projects.timesheets.manage_own'))->toBeTrue();
    expect($projectUser->permissions->pluck('slug')->contains('projects.billables.view'))->toBeFalse();
    expect($financeManager->permissions->pluck('slug')->contains('projects.invoices.create'))->toBeTrue();
    expect($approver->permissions->pluck('slug')->contains('projects.timesheets.approve'))->toBeTrue();
});

test('project user can access assigned project work but not manage the project record', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    $projectUser = User::factory()->create();

    $company->users()->attach($projectUser->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    assignProjectsCompanyRole($manager, $company->id, 'project_manager');
    assignProjectsCompanyRole($projectUser, $company->id, 'project_user');

    $records = makeProjectsAuthorizationScenario(
        companyId: $company->id,
        creator: $manager,
        projectManager: $manager,
        taskAssignee: $projectUser,
    );

    expect($projectUser->can('view', $records['project']))->toBeTrue();
    expect($projectUser->can('update', $records['project']))->toBeFalse();
    expect($projectUser->can('view', $records['task']))->toBeTrue();
    expect($projectUser->can('update', $records['task']))->toBeTrue();
    expect($projectUser->can('assign', $records['task']))->toBeFalse();
    expect($projectUser->can('view', $records['timesheet']))->toBeTrue();
    expect($projectUser->can('update', $records['timesheet']))->toBeTrue();
    expect($projectUser->can('approve', $records['timesheet']))->toBeFalse();
    expect($projectUser->can('view', $records['billable']))->toBeFalse();
});

test('project user cannot access unassigned project records outside own scope', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    $projectUser = User::factory()->create();
    $assignee = User::factory()->create();

    $company->users()->attach($projectUser->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    $company->users()->attach($assignee->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    assignProjectsCompanyRole($manager, $company->id, 'project_manager');
    assignProjectsCompanyRole($projectUser, $company->id, 'project_user');
    assignProjectsCompanyRole($assignee, $company->id, 'project_user');

    $records = makeProjectsAuthorizationScenario(
        companyId: $company->id,
        creator: $manager,
        projectManager: $manager,
        taskAssignee: $assignee,
        attachAssigneeToProject: true,
    );

    expect($projectUser->can('view', $records['project']))->toBeFalse();
    expect($projectUser->can('view', $records['task']))->toBeFalse();
    expect($projectUser->can('view', $records['timesheet']))->toBeFalse();
    expect($projectUser->can('view', $records['milestone']))->toBeFalse();
});

test('project manager can manage team project records and approve submitted timesheets', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    $projectUser = User::factory()->create();

    $company->users()->attach($projectUser->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    assignProjectsCompanyRole($manager, $company->id, 'project_manager');
    assignProjectsCompanyRole($projectUser, $company->id, 'project_user');

    $records = makeProjectsAuthorizationScenario(
        companyId: $company->id,
        creator: $projectUser,
        projectManager: $manager,
        taskAssignee: $projectUser,
        timesheetApprovalStatus: ProjectTimesheet::APPROVAL_STATUS_SUBMITTED,
    );

    expect($manager->can('view', $records['project']))->toBeTrue();
    expect($manager->can('update', $records['project']))->toBeTrue();
    expect($manager->can('assign', $records['task']))->toBeTrue();
    expect($manager->can('approve', $records['timesheet']))->toBeTrue();
    expect($manager->can('update', $records['milestone']))->toBeTrue();
    expect($manager->can('approve', $records['billable']))->toBeTrue();
    expect($manager->can('createInvoice', $records['billable']))->toBeTrue();
});

test('finance manager can access project billables and invoice actions without project membership', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    $financeManager = User::factory()->create();
    $projectUser = User::factory()->create();

    $company->users()->attach($financeManager->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    $company->users()->attach($projectUser->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    assignProjectsCompanyRole($manager, $company->id, 'project_manager');
    assignProjectsCompanyRole($financeManager, $company->id, 'finance_manager');
    assignProjectsCompanyRole($projectUser, $company->id, 'project_user');

    $records = makeProjectsAuthorizationScenario(
        companyId: $company->id,
        creator: $manager,
        projectManager: $manager,
        taskAssignee: $projectUser,
        timesheetApprovalStatus: ProjectTimesheet::APPROVAL_STATUS_APPROVED,
    );

    expect($financeManager->can('view', $records['project']))->toBeTrue();
    expect($financeManager->can('view', $records['billable']))->toBeTrue();
    expect($financeManager->can('update', $records['billable']))->toBeTrue();
    expect($financeManager->can('approve', $records['billable']))->toBeTrue();
    expect($financeManager->can('createInvoice', $records['billable']))->toBeTrue();
});
