<?php

use App\Core\Attachments\Models\Attachment;
use App\Core\MasterData\Models\Currency;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectTask;
use Database\Seeders\CoreRolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function assignProjectsSurfaceRole(User $user, string $companyId, string $roleSlug): void
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

function createProjectsSurfaceCurrency(string $companyId, string $userId): Currency
{
    return Currency::create([
        'company_id' => $companyId,
        'code' => 'PF'.Str::upper(Str::random(1)),
        'name' => 'Project Files Currency '.Str::upper(Str::random(4)),
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

test('project files can be uploaded downloaded and removed and appear in the activity feed', function () {
    Storage::fake('attachments');
    config()->set('core.attachments.disk', 'attachments');

    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    assignProjectsSurfaceRole($manager, $company->id, 'project_manager');
    $currency = createProjectsSurfaceCurrency($company->id, $manager->id);

    $project = Project::create([
        'company_id' => $company->id,
        'currency_id' => $currency->id,
        'project_code' => 'PRJ-FILES-001',
        'name' => 'Project Files Workspace',
        'status' => Project::STATUS_ACTIVE,
        'billing_type' => Project::BILLING_TYPE_TIME_AND_MATERIAL,
        'project_manager_id' => $manager->id,
        'start_date' => now()->toDateString(),
        'health_status' => Project::HEALTH_STATUS_ON_TRACK,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    ProjectTask::create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'stage_id' => null,
        'task_number' => 'TASK-FILES-001',
        'title' => 'Task activity seed',
        'status' => ProjectTask::STATUS_TODO,
        'priority' => ProjectTask::PRIORITY_MEDIUM,
        'assigned_to' => $manager->id,
        'start_date' => now()->toDateString(),
        'due_date' => now()->addDays(3)->toDateString(),
        'estimated_hours' => 3,
        'is_billable' => true,
        'billing_status' => ProjectTask::BILLING_STATUS_NOT_READY,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    actingAs($manager)
        ->post(route('company.projects.files.store', $project), [
            'file' => UploadedFile::fake()->create(
                'scope.pdf',
                20,
                'application/pdf',
            ),
        ])
        ->assertRedirect();

    $attachment = Attachment::query()->latest('created_at')->first();

    expect($attachment)->not->toBeNull();
    expect($attachment?->attachable_type)->toBe(Project::class);
    expect($attachment?->attachable_id)->toBe($project->id);

    Storage::disk('attachments')->assertExists($attachment?->path ?? '');

    actingAs($manager)
        ->get(route('company.projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/projects/show')
            ->has('attachments', 1)
            ->where('attachments.0.original_name', 'scope.pdf')
            ->has('activity')
            ->where('activity', fn ($activity) => collect($activity)->contains(
                fn (array $entry) => ($entry['action'] ?? null) === 'file_uploaded'
                    && ($entry['subject_label'] ?? null) === 'scope.pdf'
            )));

    actingAs($manager)
        ->get(route('company.projects.files.download', $attachment))
        ->assertOk();

    actingAs($manager)
        ->delete(route('company.projects.files.destroy', $attachment))
        ->assertRedirect();

    Storage::disk('attachments')->assertMissing($attachment?->path ?? '');

    actingAs($manager)
        ->get(route('company.projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/projects/show')
            ->where('activity', fn ($activity) => collect($activity)->contains(
                fn (array $entry) => ($entry['action'] ?? null) === 'file_deleted'
                    && ($entry['subject_label'] ?? null) === 'scope.pdf'
            )));
});
