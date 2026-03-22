<?php

use App\Core\MasterData\Models\Currency;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Projects\Models\ProjectTask;
use App\Notifications\ProjectActivityNotification;
use Database\Seeders\CoreRolesSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function assignProjectsNotificationRole(User $user, string $companyId, string $roleSlug): void
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

function createProjectsNotificationCurrency(string $companyId, string $userId): Currency
{
    return Currency::create([
        'company_id' => $companyId,
        'code' => 'N'.Str::upper(Str::random(2)),
        'name' => 'Project Notify Currency '.Str::upper(Str::random(4)),
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

test('project workflow sends assignment and timesheet notifications', function () {
    Notification::fake();
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    $member = User::factory()->create();

    $company->users()->attach($member->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    assignProjectsNotificationRole($manager, $company->id, 'project_manager');
    assignProjectsNotificationRole($member, $company->id, 'project_user');

    $currency = createProjectsNotificationCurrency($company->id, $manager->id);

    $project = Project::create([
        'company_id' => $company->id,
        'currency_id' => $currency->id,
        'project_code' => 'PRJ-NOTIFY-001',
        'name' => 'Project Notification Workspace',
        'status' => Project::STATUS_ACTIVE,
        'billing_type' => Project::BILLING_TYPE_TIME_AND_MATERIAL,
        'project_manager_id' => $manager->id,
        'start_date' => now()->toDateString(),
        'health_status' => Project::HEALTH_STATUS_ON_TRACK,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    ProjectMember::create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'user_id' => $manager->id,
        'project_role' => ProjectMember::ROLE_MANAGER,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    ProjectMember::create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'user_id' => $member->id,
        'project_role' => ProjectMember::ROLE_MEMBER,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    actingAs($manager)
        ->post(route('company.projects.tasks.store', $project), [
            'task_number' => 'TASK-NOTIFY-001',
            'title' => 'Assigned notification task',
            'description' => 'Task used to verify notifications.',
            'stage_id' => null,
            'parent_task_id' => null,
            'customer_id' => null,
            'status' => ProjectTask::STATUS_TODO,
            'priority' => ProjectTask::PRIORITY_HIGH,
            'assigned_to' => $member->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'estimated_hours' => 4,
            'is_billable' => true,
            'billing_status' => ProjectTask::BILLING_STATUS_NOT_READY,
        ])
        ->assertRedirect();

    Notification::assertSentTo(
        $member,
        ProjectActivityNotification::class,
        fn (ProjectActivityNotification $notification) => $notification->title === 'Task assigned'
            && ($notification->meta['task_number'] ?? null) === 'TASK-NOTIFY-001'
    );

    $task = ProjectTask::query()->where('task_number', 'TASK-NOTIFY-001')->firstOrFail();

    actingAs($member)
        ->post(route('company.projects.timesheets.store', $project), [
            'task_id' => $task->id,
            'work_date' => now()->toDateString(),
            'description' => 'Time entry for notification testing.',
            'hours' => 2,
            'is_billable' => true,
        ])
        ->assertRedirect();

    $timesheet = $project->timesheets()->latest('created_at')->firstOrFail();

    actingAs($member)
        ->post(route('company.projects.timesheets.submit', $timesheet))
        ->assertRedirect();

    Notification::assertSentTo(
        $manager,
        ProjectActivityNotification::class,
        fn (ProjectActivityNotification $notification) => $notification->title === 'Timesheet submitted'
            && ($notification->meta['timesheet_id'] ?? null) === $timesheet->id
    );

    actingAs($manager)
        ->post(route('company.projects.timesheets.approve', $timesheet))
        ->assertRedirect();

    Notification::assertSentTo(
        $member,
        ProjectActivityNotification::class,
        fn (ProjectActivityNotification $notification) => $notification->title === 'Timesheet Approved'
            && ($notification->meta['decision'] ?? null) === 'approved'
    );
});
