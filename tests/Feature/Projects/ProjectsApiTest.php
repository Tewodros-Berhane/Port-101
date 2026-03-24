<?php

use App\Core\MasterData\Models\Currency;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectTimesheet;
use Database\Seeders\CoreRolesSeeder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function assignProjectsApiRole(User $user, string $companyId, string $roleSlug): void
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

function createProjectsApiCurrency(string $companyId, string $userId): Currency
{
    return Currency::create([
        'company_id' => $companyId,
        'code' => 'P'.Str::upper(Str::random(2)),
        'name' => 'Projects API Currency '.Str::upper(Str::random(4)),
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

test('api v1 project endpoints are company scoped and support nested task and timesheet workflows', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    [$otherManager, $otherCompany] = makeActiveCompanyMember();

    assignProjectsApiRole($manager, $company->id, 'project_manager');
    assignProjectsApiRole($otherManager, $otherCompany->id, 'project_manager');

    $currency = createProjectsApiCurrency($company->id, $manager->id);
    $otherCurrency = createProjectsApiCurrency($otherCompany->id, $otherManager->id);

    $inCompanyProject = Project::create([
        'company_id' => $company->id,
        'currency_id' => $currency->id,
        'project_code' => 'PRJ-API-EXIST',
        'name' => 'Existing API Project',
        'status' => Project::STATUS_ACTIVE,
        'billing_type' => Project::BILLING_TYPE_TIME_AND_MATERIAL,
        'project_manager_id' => $manager->id,
        'start_date' => now()->toDateString(),
        'health_status' => Project::HEALTH_STATUS_ON_TRACK,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    $outOfCompanyProject = Project::create([
        'company_id' => $otherCompany->id,
        'currency_id' => $otherCurrency->id,
        'project_code' => 'PRJ-API-OTHER',
        'name' => 'Other Company Project',
        'status' => Project::STATUS_ACTIVE,
        'billing_type' => Project::BILLING_TYPE_TIME_AND_MATERIAL,
        'project_manager_id' => $otherManager->id,
        'start_date' => now()->toDateString(),
        'health_status' => Project::HEALTH_STATUS_ON_TRACK,
        'created_by' => $otherManager->id,
        'updated_by' => $otherManager->id,
    ]);

    Sanctum::actingAs($manager);

    getJson('/api/v1/projects?status=active&sort=name&direction=asc&per_page=500')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $inCompanyProject->id)
        ->assertJsonPath('meta.per_page', 100)
        ->assertJsonPath('meta.sort', 'name')
        ->assertJsonPath('meta.direction', 'asc')
        ->assertJsonPath('meta.filters.status', 'active');

    getJson('/api/v1/projects/'.$inCompanyProject->id)
        ->assertOk()
        ->assertJsonPath('data.project_code', 'PRJ-API-EXIST');

    getJson('/api/v1/projects/'.$outOfCompanyProject->id)
        ->assertNotFound()
        ->assertJsonPath('message', 'Resource not found.');

    $projectResponse = postJson('/api/v1/projects', [
        'external_reference' => 'EXT-PROJECT-001',
        'project_code' => 'PRJ-API-001',
        'name' => 'API Delivery Rollout',
        'description' => 'Created from the projects API.',
        'customer_id' => null,
        'sales_order_id' => null,
        'currency_id' => $currency->id,
        'status' => Project::STATUS_DRAFT,
        'billing_type' => Project::BILLING_TYPE_TIME_AND_MATERIAL,
        'project_manager_id' => $manager->id,
        'start_date' => now()->toDateString(),
        'target_end_date' => now()->addWeek()->toDateString(),
        'budget_amount' => 1200,
        'budget_hours' => 16,
        'progress_percent' => 0,
        'health_status' => Project::HEALTH_STATUS_ON_TRACK,
    ])
        ->assertCreated()
        ->assertJsonPath('data.external_reference', 'EXT-PROJECT-001')
        ->assertJsonPath('data.project_code', 'PRJ-API-001');

    $projectId = (string) $projectResponse->json('data.id');

    getJson('/api/v1/projects?external_reference=EXT-PROJECT-001&status=draft')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $projectId)
        ->assertJsonPath('meta.filters.external_reference', 'EXT-PROJECT-001');

    $taskResponse = postJson("/api/v1/projects/{$projectId}/tasks", [
        'task_number' => 'TASK-API-001',
        'title' => 'API Kickoff',
        'description' => 'Create kickoff task through the API.',
        'stage_id' => null,
        'parent_task_id' => null,
        'customer_id' => null,
        'status' => 'todo',
        'priority' => 'high',
        'assigned_to' => $manager->id,
        'start_date' => now()->toDateString(),
        'due_date' => now()->addDays(3)->toDateString(),
        'estimated_hours' => 6,
        'is_billable' => true,
        'billing_status' => 'not_ready',
    ])
        ->assertCreated()
        ->assertJsonPath('data.project_id', $projectId)
        ->assertJsonPath('data.task_number', 'TASK-API-001');

    $taskId = (string) $taskResponse->json('data.id');

    getJson("/api/v1/projects/{$projectId}/tasks?sort=task_number&direction=desc")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $taskId)
        ->assertJsonPath('meta.sort', 'task_number')
        ->assertJsonPath('meta.direction', 'desc');

    $timesheetResponse = postJson("/api/v1/projects/{$projectId}/timesheets", [
        'task_id' => $taskId,
        'work_date' => now()->toDateString(),
        'description' => 'Initial implementation work',
        'hours' => 2.5,
        'is_billable' => true,
        'cost_rate' => 50,
        'bill_rate' => 110,
    ])
        ->assertCreated()
        ->assertJsonPath('data.project_id', $projectId)
        ->assertJsonPath('data.approval_status', ProjectTimesheet::APPROVAL_STATUS_DRAFT);

    $timesheetId = (string) $timesheetResponse->json('data.id');

    postJson("/api/v1/projects/timesheets/{$timesheetId}/submit")
        ->assertOk()
        ->assertJsonPath('data.approval_status', ProjectTimesheet::APPROVAL_STATUS_SUBMITTED);

    postJson("/api/v1/projects/timesheets/{$timesheetId}/approve")
        ->assertOk()
        ->assertJsonPath('data.approval_status', ProjectTimesheet::APPROVAL_STATUS_APPROVED)
        ->assertJsonPath('data.invoice_status', ProjectTimesheet::INVOICE_STATUS_READY);

    getJson("/api/v1/projects/{$projectId}/timesheets?approval_status=approved&sort=work_date&direction=desc")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $timesheetId)
        ->assertJsonPath('meta.sort', 'work_date')
        ->assertJsonPath('meta.direction', 'desc')
        ->assertJsonPath('meta.filters.approval_status', ProjectTimesheet::APPROVAL_STATUS_APPROVED);
});
