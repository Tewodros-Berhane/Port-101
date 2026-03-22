<?php

use App\Core\MasterData\Models\Currency;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Projects\Models\ProjectStage;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Projects\Models\ProjectTimesheet;
use Database\Seeders\CoreRolesSeeder;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function projectsTimesheetAssignRole(User $user, string $companyId, string $roleSlug): void
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

function projectsTimesheetCurrency(string $companyId, string $userId): Currency
{
    return Currency::create([
        'company_id' => $companyId,
        'code' => 'P'.Str::upper(Str::random(2)),
        'name' => 'Project Timesheet Currency '.Str::upper(Str::random(4)),
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

/**
 * @return array{project: Project, stage: ProjectStage, task: ProjectTask}
 */
function projectsTimesheetScenario(
    string $companyId,
    User $manager,
    User $member,
    Currency $currency,
): array {
    $project = Project::create([
        'company_id' => $companyId,
        'currency_id' => $currency->id,
        'project_code' => 'PRJ-TS-'.Str::upper(Str::random(4)),
        'name' => 'Project Timesheet '.Str::upper(Str::random(4)),
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
        'hourly_cost_rate' => 70,
        'hourly_bill_rate' => 140,
        'is_billable_by_default' => true,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    ProjectMember::create([
        'company_id' => $companyId,
        'project_id' => $project->id,
        'user_id' => $member->id,
        'project_role' => ProjectMember::ROLE_MEMBER,
        'hourly_cost_rate' => 45,
        'hourly_bill_rate' => 110,
        'is_billable_by_default' => true,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    $stage = ProjectStage::create([
        'company_id' => $companyId,
        'name' => 'Execution',
        'sequence' => 1,
        'color' => 'blue',
        'is_closed_stage' => false,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    $task = ProjectTask::create([
        'company_id' => $companyId,
        'project_id' => $project->id,
        'stage_id' => $stage->id,
        'task_number' => 'TASK-TS-'.Str::upper(Str::random(4)),
        'title' => 'Implementation work',
        'status' => ProjectTask::STATUS_IN_PROGRESS,
        'priority' => ProjectTask::PRIORITY_HIGH,
        'assigned_to' => $member->id,
        'start_date' => now()->toDateString(),
        'due_date' => now()->addDays(5)->toDateString(),
        'estimated_hours' => 16,
        'is_billable' => true,
        'billing_status' => ProjectTask::BILLING_STATUS_NOT_READY,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    return [
        'project' => $project,
        'stage' => $stage,
        'task' => $task,
    ];
}

test('project user can create submit and project manager can approve timesheets', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    $member = User::factory()->create();

    $company->users()->attach($member->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    projectsTimesheetAssignRole($manager, $company->id, 'project_manager');
    projectsTimesheetAssignRole($member, $company->id, 'project_user');

    $currency = projectsTimesheetCurrency($company->id, $manager->id);
    $records = projectsTimesheetScenario($company->id, $manager, $member, $currency);

    actingAs($member)
        ->get(route('company.projects.timesheets.create', $records['project']))
        ->assertOk();

    actingAs($member)
        ->post(route('company.projects.timesheets.store', $records['project']), [
            'user_id' => $manager->id,
            'task_id' => $records['task']->id,
            'work_date' => now()->toDateString(),
            'description' => 'Logged implementation work.',
            'hours' => 2.5,
            'is_billable' => true,
        ])
        ->assertRedirect();

    $timesheet = ProjectTimesheet::query()->latest('created_at')->first();

    expect($timesheet)->not->toBeNull();
    expect($timesheet?->user_id)->toBe($member->id);
    expect((float) $timesheet?->cost_rate)->toBe(45.0);
    expect((float) $timesheet?->bill_rate)->toBe(110.0);
    expect((float) $timesheet?->cost_amount)->toBe(112.5);
    expect((float) $timesheet?->billable_amount)->toBe(275.0);
    expect($timesheet?->approval_status)->toBe(ProjectTimesheet::APPROVAL_STATUS_DRAFT);

    actingAs($member)
        ->post(route('company.projects.timesheets.submit', $timesheet))
        ->assertRedirect(route('company.projects.timesheets.edit', $timesheet));

    expect($timesheet->fresh()?->approval_status)
        ->toBe(ProjectTimesheet::APPROVAL_STATUS_SUBMITTED);

    actingAs($manager)
        ->post(route('company.projects.timesheets.approve', $timesheet))
        ->assertRedirect(route('company.projects.timesheets.edit', $timesheet));

    $timesheet = $timesheet->fresh();

    expect($timesheet?->approval_status)->toBe(ProjectTimesheet::APPROVAL_STATUS_APPROVED);
    expect($timesheet?->approved_by)->toBe($manager->id);
    expect($timesheet?->invoice_status)->toBe(ProjectTimesheet::INVOICE_STATUS_READY);

    expect((float) $records['task']->fresh()?->actual_hours)->toBe(2.5);
    expect((float) $records['project']->fresh()?->actual_cost_amount)->toBe(112.5);
});

test('project manager can create team timesheets with custom rates and reject them', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    $member = User::factory()->create();

    $company->users()->attach($member->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    projectsTimesheetAssignRole($manager, $company->id, 'project_manager');
    projectsTimesheetAssignRole($member, $company->id, 'project_user');

    $currency = projectsTimesheetCurrency($company->id, $manager->id);
    $records = projectsTimesheetScenario($company->id, $manager, $member, $currency);

    actingAs($manager)
        ->post(route('company.projects.timesheets.store', $records['project']), [
            'user_id' => $member->id,
            'task_id' => $records['task']->id,
            'work_date' => now()->toDateString(),
            'description' => 'Override team effort.',
            'hours' => 3,
            'is_billable' => true,
            'cost_rate' => 50,
            'bill_rate' => 125,
        ])
        ->assertRedirect();

    $timesheet = ProjectTimesheet::query()->latest('created_at')->firstOrFail();

    expect((float) $timesheet->cost_rate)->toBe(50.0);
    expect((float) $timesheet->bill_rate)->toBe(125.0);

    actingAs($manager)
        ->post(route('company.projects.timesheets.submit', $timesheet))
        ->assertRedirect();

    actingAs($manager)
        ->post(route('company.projects.timesheets.reject', $timesheet), [
            'reason' => 'Needs more detail.',
        ])
        ->assertRedirect(route('company.projects.timesheets.edit', $timesheet));

    $timesheet = $timesheet->fresh();

    expect($timesheet->approval_status)->toBe(ProjectTimesheet::APPROVAL_STATUS_REJECTED);
    expect($timesheet->rejection_reason)->toBe('Needs more detail.');
    expect($timesheet->invoice_status)->toBe(ProjectTimesheet::INVOICE_STATUS_NOT_READY);
});

test('deleting a draft timesheet refreshes task and project rollups', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    $member = User::factory()->create();

    $company->users()->attach($member->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    projectsTimesheetAssignRole($manager, $company->id, 'project_manager');
    projectsTimesheetAssignRole($member, $company->id, 'project_user');

    $currency = projectsTimesheetCurrency($company->id, $manager->id);
    $records = projectsTimesheetScenario($company->id, $manager, $member, $currency);

    $timesheet = ProjectTimesheet::create([
        'company_id' => $company->id,
        'project_id' => $records['project']->id,
        'task_id' => $records['task']->id,
        'user_id' => $member->id,
        'work_date' => now()->toDateString(),
        'description' => 'Initial work.',
        'hours' => 4,
        'is_billable' => true,
        'cost_rate' => 45,
        'bill_rate' => 110,
        'cost_amount' => 180,
        'billable_amount' => 440,
        'approval_status' => ProjectTimesheet::APPROVAL_STATUS_DRAFT,
        'invoice_status' => ProjectTimesheet::INVOICE_STATUS_NOT_READY,
        'created_by' => $member->id,
        'updated_by' => $member->id,
    ]);

    $records['task']->forceFill(['actual_hours' => 4])->save();
    $records['project']->forceFill(['actual_cost_amount' => 180])->save();

    actingAs($member)
        ->delete(route('company.projects.timesheets.destroy', $timesheet))
        ->assertRedirect(route('company.projects.show', $records['project']));

    $this->assertSoftDeleted('project_timesheets', [
        'id' => $timesheet->id,
    ]);

    expect((float) $records['task']->fresh()?->actual_hours)->toBe(0.0);
    expect((float) $records['project']->fresh()?->actual_cost_amount)->toBe(0.0);
});
