<?php

use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectRecurringBilling;
use App\Modules\Projects\Models\ProjectRecurringBillingRun;
use App\Modules\Projects\ProjectRecurringBillingService;
use Database\Seeders\CoreRolesSeeder;
use Illuminate\Support\Str;

function assignProjectRecurringRole(User $user, string $companyId, string $roleSlug): void
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

function createProjectRecurringCurrency(string $companyId, string $actorId): Currency
{
    return Currency::create([
        'company_id' => $companyId,
        'code' => 'RC'.Str::upper(Str::random(1)),
        'name' => 'Recurring Currency '.Str::upper(Str::random(4)),
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $actorId,
        'updated_by' => $actorId,
    ]);
}

function createProjectRecurringCustomer(string $companyId, string $actorId): Partner
{
    return Partner::create([
        'company_id' => $companyId,
        'code' => 'RCUST-'.Str::upper(Str::random(4)),
        'name' => 'Recurring Customer '.Str::upper(Str::random(4)),
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $actorId,
        'updated_by' => $actorId,
    ]);
}

function createProjectRecurringProject(
    string $companyId,
    User $manager,
    Partner $customer,
    Currency $currency,
): Project {
    return Project::create([
        'company_id' => $companyId,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'project_code' => 'PRJ-RC-'.Str::upper(Str::random(4)),
        'name' => 'Recurring Services '.Str::upper(Str::random(4)),
        'description' => 'Managed services retainer',
        'status' => Project::STATUS_ACTIVE,
        'billing_type' => Project::BILLING_TYPE_FIXED_FEE,
        'project_manager_id' => $manager->id,
        'start_date' => now()->subMonth()->toDateString(),
        'target_end_date' => now()->addMonths(6)->toDateString(),
        'budget_amount' => 12000,
        'budget_hours' => 120,
        'health_status' => Project::HEALTH_STATUS_ON_TRACK,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);
}

function createProjectRecurringSchedule(
    Project $project,
    Currency $currency,
    Partner $customer,
    string $actorId,
    array $overrides = [],
): ProjectRecurringBilling {
    return ProjectRecurringBilling::create([
        'company_id' => $project->company_id,
        'project_id' => $project->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'name' => 'Managed Services Retainer',
        'description' => 'Monthly managed services subscription',
        'frequency' => ProjectRecurringBilling::FREQUENCY_MONTHLY,
        'quantity' => 2,
        'unit_price' => 350,
        'invoice_due_days' => 30,
        'starts_on' => now()->subMonth()->startOfMonth()->toDateString(),
        'next_run_on' => now()->startOfDay()->toDateString(),
        'ends_on' => now()->addMonths(6)->endOfMonth()->toDateString(),
        'auto_create_invoice_draft' => false,
        'invoice_grouping' => 'project',
        'status' => ProjectRecurringBilling::STATUS_ACTIVE,
        'created_by' => $actorId,
        'updated_by' => $actorId,
        ...$overrides,
    ]);
}

test('due recurring billing schedules generate recurring project billables and advance the next run date', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    assignProjectRecurringRole($manager, $company->id, 'project_manager');

    $currency = createProjectRecurringCurrency($company->id, $manager->id);
    $customer = createProjectRecurringCustomer($company->id, $manager->id);
    $project = createProjectRecurringProject($company->id, $manager, $customer, $currency);
    $schedule = createProjectRecurringSchedule($project, $currency, $customer, $manager->id);

    $this->artisan('projects:recurring-billing:run', ['companyId' => $company->id])
        ->assertSuccessful();

    $run = ProjectRecurringBillingRun::query()
        ->with('billable')
        ->where('project_recurring_billing_id', $schedule->id)
        ->first();

    expect($run)->not->toBeNull();
    expect($run?->status)->toBe(ProjectRecurringBillingRun::STATUS_READY);
    expect($run?->billable?->billable_type)->toBe(ProjectBillable::TYPE_RECURRING);
    expect((float) $run?->billable?->amount)->toBe(700.0);
    expect((float) $run?->billable?->quantity)->toBe(2.0);
    expect($run?->billable?->invoice_id)->toBeNull();
    expect($schedule->fresh()?->next_run_on?->toDateString())
        ->toBe(now()->startOfDay()->addMonthNoOverflow()->toDateString());
});

test('auto-invoice recurring billing schedules create accounting draft invoices', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    assignProjectRecurringRole($manager, $company->id, 'finance_manager');

    $currency = createProjectRecurringCurrency($company->id, $manager->id);
    $customer = createProjectRecurringCustomer($company->id, $manager->id);
    $project = createProjectRecurringProject($company->id, $manager, $customer, $currency);
    $schedule = createProjectRecurringSchedule(
        $project,
        $currency,
        $customer,
        $manager->id,
        [
            'auto_create_invoice_draft' => true,
            'invoice_due_days' => 15,
        ],
    );

    $this->artisan('projects:recurring-billing:run', ['companyId' => $company->id])
        ->assertSuccessful();

    $run = ProjectRecurringBillingRun::query()
        ->where('project_recurring_billing_id', $schedule->id)
        ->first();
    $invoice = AccountingInvoice::query()->first();

    expect($run?->status)->toBe(ProjectRecurringBillingRun::STATUS_INVOICED);
    expect($invoice)->not->toBeNull();
    expect($invoice?->status)->toBe(AccountingInvoice::STATUS_DRAFT);
    expect($invoice?->partner_id)->toBe($customer->id);
    expect($invoice?->invoice_date?->toDateString())->toBe(now()->startOfDay()->toDateString());
    expect($invoice?->due_date?->toDateString())->toBe(now()->startOfDay()->addDays(15)->toDateString());
    expect((float) $invoice?->grand_total)->toBe(700.0);
    expect((string) $invoice?->notes)->toContain('Managed Services Retainer');
    expect($schedule->fresh()?->last_invoice_id)->toBe($invoice?->id);
    expect($run?->invoice_id)->toBe($invoice?->id);
});

test('recurring billing processing is idempotent for an already processed cycle', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    assignProjectRecurringRole($manager, $company->id, 'project_manager');

    $currency = createProjectRecurringCurrency($company->id, $manager->id);
    $customer = createProjectRecurringCustomer($company->id, $manager->id);
    $project = createProjectRecurringProject($company->id, $manager, $customer, $currency);
    $schedule = createProjectRecurringSchedule($project, $currency, $customer, $manager->id);

    $this->artisan('projects:recurring-billing:run', ['companyId' => $company->id])
        ->assertSuccessful();
    $this->artisan('projects:recurring-billing:run', ['companyId' => $company->id])
        ->assertSuccessful();

    expect(ProjectRecurringBillingRun::query()
        ->where('project_recurring_billing_id', $schedule->id)
        ->count())->toBe(1);
    expect(ProjectBillable::query()
        ->where('source_type', ProjectRecurringBillingRun::class)
        ->count())->toBe(1);
});

test('project recurring billing can run immediately even before the next due date', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    assignProjectRecurringRole($manager, $company->id, 'project_manager');

    $currency = createProjectRecurringCurrency($company->id, $manager->id);
    $customer = createProjectRecurringCustomer($company->id, $manager->id);
    $project = createProjectRecurringProject($company->id, $manager, $customer, $currency);
    $schedule = createProjectRecurringSchedule(
        $project,
        $currency,
        $customer,
        $manager->id,
        [
            'next_run_on' => now()->startOfDay()->addDays(7)->toDateString(),
        ],
    );

    $run = app(ProjectRecurringBillingService::class)->runNow($schedule, $manager->id);

    expect($run)->not->toBeNull();
    expect($run?->status)->toBe(ProjectRecurringBillingRun::STATUS_READY);
    expect($schedule->fresh()?->next_run_on?->toDateString())
        ->toBe(now()->startOfDay()->addDays(7)->addMonthNoOverflow()->toDateString());
});
