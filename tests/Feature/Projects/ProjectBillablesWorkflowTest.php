<?php

use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\RBAC\Models\Role;
use App\Core\Settings\SettingsService;
use App\Models\User;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Modules\Projects\ProjectBillingService;
use Database\Seeders\CoreRolesSeeder;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function projectsBillableWorkflowAssignRole(User $user, string $companyId, string $roleSlug): void
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

function projectsBillableWorkflowCurrency(string $companyId, string $userId): Currency
{
    return Currency::create([
        'company_id' => $companyId,
        'code' => 'BW'.Str::upper(Str::random(1)),
        'name' => 'Projects Workflow Currency '.Str::upper(Str::random(4)),
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

function projectsBillableWorkflowCustomer(string $companyId, string $userId): Partner
{
    return Partner::create([
        'company_id' => $companyId,
        'code' => 'BWC-'.Str::upper(Str::random(4)),
        'name' => 'Project Billable Customer '.Str::upper(Str::random(4)),
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

function projectsBillableWorkflowProject(
    string $companyId,
    User $manager,
    Currency $currency,
    Partner $customer,
): Project {
    $project = Project::create([
        'company_id' => $companyId,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'project_code' => 'PRJ-BW-'.Str::upper(Str::random(4)),
        'name' => 'Project Billable Workflow '.Str::upper(Str::random(4)),
        'status' => Project::STATUS_ACTIVE,
        'billing_type' => Project::BILLING_TYPE_FIXED_FEE,
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

    return $project;
}

function projectsBillableWorkflowApprovedMilestone(
    Project $project,
    User $manager,
    float $amount,
): ProjectMilestone {
    return ProjectMilestone::create([
        'company_id' => $project->company_id,
        'project_id' => $project->id,
        'name' => 'Approval milestone '.Str::upper(Str::random(3)),
        'sequence' => 1,
        'status' => ProjectMilestone::STATUS_APPROVED,
        'due_date' => now()->addDays(5)->toDateString(),
        'completed_at' => now(),
        'approved_by' => $manager->id,
        'approved_at' => now(),
        'amount' => $amount,
        'invoice_status' => ProjectMilestone::INVOICE_STATUS_READY,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);
}

function enableProjectBillableApprovals(string $companyId, string $actorId, float $threshold): void
{
    /** @var SettingsService $settings */
    $settings = app(SettingsService::class);

    $settings->set('company.approvals.enabled', true, $companyId, null, $actorId);
    $settings->set('company.approvals.policy', 'amount_based', $companyId, null, $actorId);
    $settings->set('company.approvals.threshold_amount', $threshold, $companyId, null, $actorId);
}

test('threshold-based project billables enter pending approval and finance manager can approve them', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    $financeManager = User::factory()->create();

    $company->users()->attach($financeManager->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    projectsBillableWorkflowAssignRole($manager, $company->id, 'project_manager');
    projectsBillableWorkflowAssignRole($financeManager, $company->id, 'finance_manager');

    enableProjectBillableApprovals($company->id, $manager->id, 1000);

    $currency = projectsBillableWorkflowCurrency($company->id, $manager->id);
    $customer = projectsBillableWorkflowCustomer($company->id, $manager->id);
    $project = projectsBillableWorkflowProject($company->id, $manager, $currency, $customer);
    $milestone = projectsBillableWorkflowApprovedMilestone($project, $manager, 1500);

    $billable = app(ProjectBillingService::class)->syncFromMilestone($milestone, $manager->id);

    expect($billable)->not->toBeNull();
    expect($billable?->status)->toBe(ProjectBillable::STATUS_READY);
    expect($billable?->approval_status)->toBe(ProjectBillable::APPROVAL_STATUS_PENDING);

    actingAs($financeManager)
        ->post(route('company.projects.billables.approve', $billable))
        ->assertSessionHas('success');

    expect($billable?->fresh()->status)->toBe(ProjectBillable::STATUS_APPROVED);
    expect($billable?->fresh()->approval_status)->toBe(ProjectBillable::APPROVAL_STATUS_APPROVED);
    expect($billable?->fresh()->approved_by)->toBe($financeManager->id);
});

test('project manager can reject and cancel approval-controlled billables with reasons', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    projectsBillableWorkflowAssignRole($manager, $company->id, 'project_manager');
    enableProjectBillableApprovals($company->id, $manager->id, 1000);

    $currency = projectsBillableWorkflowCurrency($company->id, $manager->id);
    $customer = projectsBillableWorkflowCustomer($company->id, $manager->id);
    $project = projectsBillableWorkflowProject($company->id, $manager, $currency, $customer);
    $milestone = projectsBillableWorkflowApprovedMilestone($project, $manager, 1750);

    $billable = app(ProjectBillingService::class)->syncFromMilestone($milestone, $manager->id);

    actingAs($manager)
        ->post(route('company.projects.billables.reject', $billable), [
            'reason' => 'Awaiting signed acceptance.',
        ])
        ->assertSessionHas('success');

    expect($billable?->fresh()->status)->toBe(ProjectBillable::STATUS_READY);
    expect($billable?->fresh()->approval_status)->toBe(ProjectBillable::APPROVAL_STATUS_REJECTED);
    expect($billable?->fresh()->rejection_reason)->toBe('Awaiting signed acceptance.');
    expect($billable?->fresh()->rejected_by)->toBe($manager->id);

    actingAs($manager)
        ->post(route('company.projects.billables.cancel', $billable), [
            'reason' => 'Duplicate milestone billing line.',
        ])
        ->assertSessionHas('success');

    expect($billable?->fresh()->status)->toBe(ProjectBillable::STATUS_CANCELLED);
    expect($billable?->fresh()->cancellation_reason)->toBe('Duplicate milestone billing line.');
    expect($billable?->fresh()->cancelled_by)->toBe($manager->id);
});

test('project billables requiring approval sync into the shared approvals queue', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    $approver = User::factory()->create();

    $company->users()->attach($approver->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    projectsBillableWorkflowAssignRole($manager, $company->id, 'project_manager');
    projectsBillableWorkflowAssignRole($approver, $company->id, 'approver');
    enableProjectBillableApprovals($company->id, $manager->id, 1000);

    $currency = projectsBillableWorkflowCurrency($company->id, $manager->id);
    $customer = projectsBillableWorkflowCustomer($company->id, $manager->id);
    $project = projectsBillableWorkflowProject($company->id, $manager, $currency, $customer);
    $milestone = projectsBillableWorkflowApprovedMilestone($project, $manager, 2200);

    $billable = app(ProjectBillingService::class)->syncFromMilestone($milestone, $manager->id);

    actingAs($approver)
        ->get(route('company.modules.approvals'))
        ->assertOk();

    $approvalRequest = ApprovalRequest::query()
        ->where('company_id', $company->id)
        ->where('source_type', ProjectBillable::class)
        ->where('source_id', $billable?->id)
        ->first();

    expect($approvalRequest)->not->toBeNull();
    expect($approvalRequest?->module)->toBe(ApprovalRequest::MODULE_PROJECTS);
    expect($approvalRequest?->action)->toBe(ApprovalRequest::ACTION_PROJECT_BILLABLE_APPROVAL);
    expect($approvalRequest?->status)->toBe(ApprovalRequest::STATUS_PENDING);

    actingAs($approver)
        ->post(route('company.approvals.approve', $approvalRequest))
        ->assertSessionHas('success');

    expect($billable?->fresh()->status)->toBe(ProjectBillable::STATUS_APPROVED);
    expect($billable?->fresh()->approval_status)->toBe(ProjectBillable::APPROVAL_STATUS_APPROVED);
    expect($billable?->fresh()->approved_by)->toBe($approver->id);
});
