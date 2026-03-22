<?php

use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Modules\Projects\Models\ProjectTimesheet;
use Database\Seeders\CoreRolesSeeder;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function assignProjectInvoiceWorkflowRole(User $user, string $companyId, string $roleSlug): void
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

function projectInvoiceWorkflowCustomer(string $companyId, string $actorId): Partner
{
    return Partner::create([
        'company_id' => $companyId,
        'code' => 'CUST-'.Str::upper(Str::random(4)),
        'name' => 'Projects Invoice Customer '.Str::upper(Str::random(4)),
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $actorId,
        'updated_by' => $actorId,
    ]);
}

function projectInvoiceWorkflowCurrency(string $companyId, string $actorId): Currency
{
    return Currency::create([
        'company_id' => $companyId,
        'code' => 'U'.Str::upper(Str::random(2)),
        'name' => 'Projects Invoice Currency '.Str::upper(Str::random(4)),
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $actorId,
        'updated_by' => $actorId,
    ]);
}

function projectInvoiceWorkflowProject(
    string $companyId,
    string $actorId,
    Partner $customer,
    Currency $currency,
): Project {
    return Project::create([
        'company_id' => $companyId,
        'customer_id' => $customer->id,
        'sales_order_id' => null,
        'currency_id' => $currency->id,
        'project_code' => 'PRJ-'.Str::upper(Str::random(4)),
        'name' => 'Projects Invoice Delivery '.Str::upper(Str::random(4)),
        'description' => 'Billing handoff project',
        'status' => Project::STATUS_ACTIVE,
        'billing_type' => Project::BILLING_TYPE_MIXED,
        'project_manager_id' => $actorId,
        'start_date' => now()->toDateString(),
        'target_end_date' => now()->addWeeks(4)->toDateString(),
        'budget_amount' => 5000,
        'budget_hours' => 80,
        'health_status' => Project::HEALTH_STATUS_ON_TRACK,
        'created_by' => $actorId,
        'updated_by' => $actorId,
    ]);
}

/**
 * @return array{timesheet: ProjectTimesheet, billable: ProjectBillable}
 */
function projectInvoiceWorkflowTimesheetBillable(
    Project $project,
    string $actorId,
    string $approvalStatus = ProjectBillable::APPROVAL_STATUS_NOT_REQUIRED,
    string $status = ProjectBillable::STATUS_READY,
): array {
    $timesheet = ProjectTimesheet::create([
        'company_id' => $project->company_id,
        'project_id' => $project->id,
        'task_id' => null,
        'user_id' => $actorId,
        'work_date' => now()->toDateString(),
        'description' => 'Implementation services',
        'hours' => 3,
        'is_billable' => true,
        'cost_rate' => 40,
        'bill_rate' => 110,
        'cost_amount' => 120,
        'billable_amount' => 330,
        'approval_status' => ProjectTimesheet::APPROVAL_STATUS_APPROVED,
        'approved_by' => $actorId,
        'approved_at' => now(),
        'invoice_status' => ProjectTimesheet::INVOICE_STATUS_READY,
        'created_by' => $actorId,
        'updated_by' => $actorId,
    ]);

    $billable = ProjectBillable::create([
        'company_id' => $project->company_id,
        'project_id' => $project->id,
        'billable_type' => ProjectBillable::TYPE_TIMESHEET,
        'source_type' => ProjectTimesheet::class,
        'source_id' => $timesheet->id,
        'customer_id' => $project->customer_id,
        'description' => 'Implementation services',
        'quantity' => 3,
        'unit_price' => 110,
        'amount' => 330,
        'currency_id' => $project->currency_id,
        'status' => $status,
        'approval_status' => $approvalStatus,
        'approved_by' => $approvalStatus === ProjectBillable::APPROVAL_STATUS_APPROVED
            ? $actorId
            : null,
        'approved_at' => $approvalStatus === ProjectBillable::APPROVAL_STATUS_APPROVED
            ? now()
            : null,
        'created_by' => $actorId,
        'updated_by' => $actorId,
    ]);

    return [
        'timesheet' => $timesheet,
        'billable' => $billable,
    ];
}

/**
 * @return array{milestone: ProjectMilestone, billable: ProjectBillable}
 */
function projectInvoiceWorkflowMilestoneBillable(
    Project $project,
    string $actorId,
    string $approvalStatus = ProjectBillable::APPROVAL_STATUS_APPROVED,
    string $status = ProjectBillable::STATUS_APPROVED,
): array {
    $milestone = ProjectMilestone::create([
        'company_id' => $project->company_id,
        'project_id' => $project->id,
        'name' => 'Go-live milestone',
        'description' => 'Final deployment and sign-off',
        'sequence' => 1,
        'status' => ProjectMilestone::STATUS_APPROVED,
        'due_date' => now()->addDays(7)->toDateString(),
        'completed_at' => now(),
        'approved_by' => $actorId,
        'approved_at' => now(),
        'amount' => 900,
        'invoice_status' => ProjectMilestone::INVOICE_STATUS_READY,
        'created_by' => $actorId,
        'updated_by' => $actorId,
    ]);

    $billable = ProjectBillable::create([
        'company_id' => $project->company_id,
        'project_id' => $project->id,
        'billable_type' => ProjectBillable::TYPE_MILESTONE,
        'source_type' => ProjectMilestone::class,
        'source_id' => $milestone->id,
        'customer_id' => $project->customer_id,
        'description' => 'Go-live milestone',
        'quantity' => 1,
        'unit_price' => 900,
        'amount' => 900,
        'currency_id' => $project->currency_id,
        'status' => $status,
        'approval_status' => $approvalStatus,
        'approved_by' => $approvalStatus === ProjectBillable::APPROVAL_STATUS_APPROVED
            ? $actorId
            : null,
        'approved_at' => $approvalStatus === ProjectBillable::APPROVAL_STATUS_APPROVED
            ? now()
            : null,
        'created_by' => $actorId,
        'updated_by' => $actorId,
    ]);

    return [
        'milestone' => $milestone,
        'billable' => $billable,
    ];
}

test('finance manager can create invoice drafts from eligible project billables', function () {
    $this->seed(CoreRolesSeeder::class);

    [$owner, $company] = makeActiveCompanyMember();
    $financeManager = User::factory()->create();

    $company->users()->attach($financeManager->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    assignProjectInvoiceWorkflowRole($financeManager, $company->id, 'finance_manager');

    $customer = projectInvoiceWorkflowCustomer($company->id, $owner->id);
    $currency = projectInvoiceWorkflowCurrency($company->id, $owner->id);
    $project = projectInvoiceWorkflowProject($company->id, $owner->id, $customer, $currency);

    $timesheetData = projectInvoiceWorkflowTimesheetBillable(
        project: $project,
        actorId: $owner->id,
        approvalStatus: ProjectBillable::APPROVAL_STATUS_NOT_REQUIRED,
        status: ProjectBillable::STATUS_READY,
    );

    $milestoneData = projectInvoiceWorkflowMilestoneBillable(
        project: $project,
        actorId: $owner->id,
        approvalStatus: ProjectBillable::APPROVAL_STATUS_APPROVED,
        status: ProjectBillable::STATUS_APPROVED,
    );

    actingAs($financeManager)
        ->from(route('company.projects.billables.index'))
        ->post(route('company.projects.billables.invoice-drafts.store'), [
            'billable_ids' => [
                $timesheetData['billable']->id,
                $milestoneData['billable']->id,
            ],
            'group_by' => 'project',
        ])
        ->assertRedirect(route('company.projects.billables.index'));

    $invoice = AccountingInvoice::query()->first();

    expect($invoice)->not->toBeNull();
    expect($invoice?->document_type)->toBe(AccountingInvoice::TYPE_CUSTOMER_INVOICE);
    expect($invoice?->status)->toBe(AccountingInvoice::STATUS_DRAFT);
    expect($invoice?->partner_id)->toBe($customer->id);
    expect($invoice?->lines()->count())->toBe(2);
    expect((float) $invoice?->grand_total)->toBe(1230.0);
    expect((string) $invoice?->notes)->toContain($project->project_code);

    expect($timesheetData['billable']->fresh()?->invoice_id)->toBe($invoice?->id);
    expect($timesheetData['billable']->fresh()?->status)->toBe(ProjectBillable::STATUS_INVOICED);
    expect($timesheetData['billable']->fresh()?->invoice_line_reference)->not->toBeNull();
    expect($timesheetData['timesheet']->fresh()?->invoice_status)->toBe(ProjectTimesheet::INVOICE_STATUS_INVOICED);

    expect($milestoneData['billable']->fresh()?->invoice_id)->toBe($invoice?->id);
    expect($milestoneData['billable']->fresh()?->status)->toBe(ProjectBillable::STATUS_INVOICED);
    expect($milestoneData['billable']->fresh()?->invoice_line_reference)->not->toBeNull();
    expect($milestoneData['milestone']->fresh()?->invoice_status)->toBe(ProjectMilestone::INVOICE_STATUS_INVOICED);
    expect($milestoneData['milestone']->fresh()?->status)->toBe(ProjectMilestone::STATUS_BILLED);
});

test('group by customer combines eligible billables across projects for the same customer', function () {
    $this->seed(CoreRolesSeeder::class);

    [$owner, $company] = makeActiveCompanyMember();
    $financeManager = User::factory()->create();

    $company->users()->attach($financeManager->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    assignProjectInvoiceWorkflowRole($financeManager, $company->id, 'finance_manager');

    $customer = projectInvoiceWorkflowCustomer($company->id, $owner->id);
    $currency = projectInvoiceWorkflowCurrency($company->id, $owner->id);
    $projectA = projectInvoiceWorkflowProject($company->id, $owner->id, $customer, $currency);
    $projectB = projectInvoiceWorkflowProject($company->id, $owner->id, $customer, $currency);

    $billableA = projectInvoiceWorkflowTimesheetBillable(
        project: $projectA,
        actorId: $owner->id,
    );
    $billableB = projectInvoiceWorkflowTimesheetBillable(
        project: $projectB,
        actorId: $owner->id,
    );

    actingAs($financeManager)
        ->from(route('company.projects.billables.index'))
        ->post(route('company.projects.billables.invoice-drafts.store'), [
            'billable_ids' => [
                $billableA['billable']->id,
                $billableB['billable']->id,
            ],
            'group_by' => 'customer',
        ])
        ->assertRedirect(route('company.projects.billables.index'));

    $invoice = AccountingInvoice::query()->first();

    expect(AccountingInvoice::query()->count())->toBe(1);
    expect($invoice)->not->toBeNull();
    expect($invoice?->lines()->count())->toBe(2);
    expect((string) $invoice?->notes)->toContain($projectA->project_code);
    expect((string) $invoice?->notes)->toContain($projectB->project_code);
    expect($billableA['billable']->fresh()?->invoice_id)->toBe($invoice?->id);
    expect($billableB['billable']->fresh()?->invoice_id)->toBe($invoice?->id);
});

test('pending or rejected project billables cannot be handed off into invoice drafts', function () {
    $this->seed(CoreRolesSeeder::class);

    [$owner, $company] = makeActiveCompanyMember();
    $financeManager = User::factory()->create();

    $company->users()->attach($financeManager->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    assignProjectInvoiceWorkflowRole($financeManager, $company->id, 'finance_manager');

    $customer = projectInvoiceWorkflowCustomer($company->id, $owner->id);
    $currency = projectInvoiceWorkflowCurrency($company->id, $owner->id);
    $project = projectInvoiceWorkflowProject($company->id, $owner->id, $customer, $currency);

    $pendingBillable = projectInvoiceWorkflowTimesheetBillable(
        project: $project,
        actorId: $owner->id,
        approvalStatus: ProjectBillable::APPROVAL_STATUS_PENDING,
        status: ProjectBillable::STATUS_READY,
    );

    actingAs($financeManager)
        ->from(route('company.projects.billables.index'))
        ->post(route('company.projects.billables.invoice-drafts.store'), [
            'billable_ids' => [$pendingBillable['billable']->id],
            'group_by' => 'project',
        ])
        ->assertRedirect(route('company.projects.billables.index'))
        ->assertSessionHas('error');

    expect(AccountingInvoice::query()->count())->toBe(0);
    expect($pendingBillable['billable']->fresh()?->invoice_id)->toBeNull();
    expect($pendingBillable['billable']->fresh()?->status)->toBe(ProjectBillable::STATUS_READY);
});
