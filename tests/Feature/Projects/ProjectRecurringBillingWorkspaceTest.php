<?php

use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectRecurringBilling;
use App\Modules\Projects\Models\ProjectRecurringBillingRun;
use Database\Seeders\CoreRolesSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function assignProjectRecurringWorkspaceRole(User $user, string $companyId, string $roleSlug): void
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

function createProjectRecurringWorkspaceCurrency(string $companyId, string $actorId): Currency
{
    return Currency::create([
        'company_id' => $companyId,
        'code' => 'RW'.Str::upper(Str::random(1)),
        'name' => 'Recurring Workspace Currency '.Str::upper(Str::random(4)),
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $actorId,
        'updated_by' => $actorId,
    ]);
}

function createProjectRecurringWorkspaceCustomer(string $companyId, string $actorId): Partner
{
    return Partner::create([
        'company_id' => $companyId,
        'code' => 'RWC-'.Str::upper(Str::random(4)),
        'name' => 'Recurring Workspace Customer '.Str::upper(Str::random(4)),
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $actorId,
        'updated_by' => $actorId,
    ]);
}

function createProjectRecurringWorkspaceProject(
    string $companyId,
    User $manager,
    Partner $customer,
    Currency $currency,
): Project {
    return Project::create([
        'company_id' => $companyId,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'project_code' => 'PRJ-RW-'.Str::upper(Str::random(4)),
        'name' => 'Recurring Workspace Project '.Str::upper(Str::random(4)),
        'status' => Project::STATUS_ACTIVE,
        'billing_type' => Project::BILLING_TYPE_FIXED_FEE,
        'project_manager_id' => $manager->id,
        'start_date' => now()->subWeek()->toDateString(),
        'target_end_date' => now()->addMonths(3)->toDateString(),
        'health_status' => Project::HEALTH_STATUS_ON_TRACK,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);
}

test('project manager can manage recurring billing schedules from the workspace', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    assignProjectRecurringWorkspaceRole($manager, $company->id, 'project_manager');

    $currency = createProjectRecurringWorkspaceCurrency($company->id, $manager->id);
    $customer = createProjectRecurringWorkspaceCustomer($company->id, $manager->id);
    $project = createProjectRecurringWorkspaceProject($company->id, $manager, $customer, $currency);

    actingAs($manager)
        ->get(route('company.projects.recurring-billing.index'))
        ->assertOk();

    actingAs($manager)
        ->get(route('company.projects.recurring-billing.create', ['project_id' => $project->id]))
        ->assertOk();

    actingAs($manager)
        ->post(route('company.projects.recurring-billing.store'), [
            'project_id' => $project->id,
            'customer_id' => $customer->id,
            'currency_id' => $currency->id,
            'name' => 'Support Retainer',
            'description' => 'Monthly support contract',
            'frequency' => ProjectRecurringBilling::FREQUENCY_MONTHLY,
            'quantity' => 1,
            'unit_price' => 1200,
            'invoice_due_days' => 14,
            'starts_on' => now()->startOfMonth()->toDateString(),
            'next_run_on' => now()->toDateString(),
            'ends_on' => now()->addMonths(6)->endOfMonth()->toDateString(),
            'auto_create_invoice_draft' => true,
            'invoice_grouping' => 'project',
            'status' => ProjectRecurringBilling::STATUS_ACTIVE,
        ])
        ->assertRedirect(route('company.projects.recurring-billing.index'));

    $schedule = ProjectRecurringBilling::query()->first();

    expect($schedule)->not->toBeNull();
    expect($schedule?->status)->toBe(ProjectRecurringBilling::STATUS_ACTIVE);

    actingAs($manager)
        ->from(route('company.projects.recurring-billing.index'))
        ->post(route('company.projects.recurring-billing.run-now', $schedule))
        ->assertRedirect(route('company.projects.recurring-billing.index'));

    expect(ProjectRecurringBillingRun::query()
        ->where('project_recurring_billing_id', $schedule?->id)
        ->count())->toBe(1);
    expect(ProjectBillable::query()
        ->where('source_type', ProjectRecurringBillingRun::class)
        ->count())->toBe(1);

    actingAs($manager)
        ->from(route('company.projects.recurring-billing.index'))
        ->post(route('company.projects.recurring-billing.pause', $schedule))
        ->assertRedirect(route('company.projects.recurring-billing.index'));

    expect($schedule?->fresh()?->status)->toBe(ProjectRecurringBilling::STATUS_PAUSED);
});

test('project user cannot access recurring billing workspace routes', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    $projectUser = User::factory()->create();

    $company->users()->attach($projectUser->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    assignProjectRecurringWorkspaceRole($manager, $company->id, 'project_manager');
    assignProjectRecurringWorkspaceRole($projectUser, $company->id, 'project_user');

    actingAs($projectUser)
        ->get(route('company.projects.recurring-billing.index'))
        ->assertForbidden();
});

test('project detail page exposes recurring billing summary and schedule rows', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    assignProjectRecurringWorkspaceRole($manager, $company->id, 'project_manager');

    $currency = createProjectRecurringWorkspaceCurrency($company->id, $manager->id);
    $customer = createProjectRecurringWorkspaceCustomer($company->id, $manager->id);
    $project = createProjectRecurringWorkspaceProject($company->id, $manager, $customer, $currency);

    ProjectRecurringBilling::create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'name' => 'Managed Services',
        'description' => 'Quarterly support retainer',
        'frequency' => ProjectRecurringBilling::FREQUENCY_QUARTERLY,
        'quantity' => 1,
        'unit_price' => 2400,
        'invoice_due_days' => 21,
        'starts_on' => now()->startOfMonth()->toDateString(),
        'next_run_on' => now()->toDateString(),
        'ends_on' => now()->addYear()->endOfMonth()->toDateString(),
        'auto_create_invoice_draft' => false,
        'invoice_grouping' => 'project',
        'status' => ProjectRecurringBilling::STATUS_ACTIVE,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    actingAs($manager)
        ->get(route('company.projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/projects/show')
            ->where('summary.recurring_billing_total', 1)
            ->where('summary.recurring_billing_active', 1)
            ->where('summary.recurring_billing_due_now', 1)
            ->where('summary.recurring_billing_amount', 2400)
            ->where('project.recurring_billings.0.name', 'Managed Services')
            ->where('project.recurring_billings.0.frequency', ProjectRecurringBilling::FREQUENCY_QUARTERLY)
            ->where('abilities.can_view_recurring', true)
            ->where('abilities.can_manage_recurring', true));
});
