<?php

use App\Core\MasterData\Models\Currency;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Accounting\Models\AccountingPayment;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrReimbursementClaim;
use Database\Seeders\CoreRolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

function assignHrReimbursementRole(User $user, string $companyId, string $roleSlug): void
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

test('reimbursement claim can complete manager approval finance approval accounting handoff and payment', function () {
    $this->seed(CoreRolesSeeder::class);

    [$owner, $company] = makeActiveCompanyMember();
    assignHrReimbursementRole($owner, $company->id, 'owner');

    $managerUser = User::factory()->create();
    $payrollUser = User::factory()->create();
    $employeeUser = User::factory()->create();

    $company->users()->syncWithoutDetaching([
        $managerUser->id => ['role_id' => null, 'is_owner' => false],
        $payrollUser->id => ['role_id' => null, 'is_owner' => false],
        $employeeUser->id => ['role_id' => null, 'is_owner' => false],
    ]);

    assignHrReimbursementRole($managerUser, $company->id, 'hr_manager');
    assignHrReimbursementRole($payrollUser, $company->id, 'payroll_manager');
    assignHrReimbursementRole($employeeUser, $company->id, 'employee_self_service');

    Currency::create([
        'company_id' => $company->id,
        'code' => (string) $company->currency_code,
        'name' => 'Company Currency',
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    $managerEmployee = HrEmployee::create([
        'company_id' => $company->id,
        'user_id' => $managerUser->id,
        'employee_number' => 'EMP-RMB-100',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'first_name' => 'Manager',
        'last_name' => 'User',
        'display_name' => 'Manager User',
        'hire_date' => now()->toDateString(),
        'timezone' => 'UTC',
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    $employee = HrEmployee::create([
        'company_id' => $company->id,
        'user_id' => $employeeUser->id,
        'employee_number' => 'EMP-RMB-200',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'first_name' => 'Expense',
        'last_name' => 'Worker',
        'display_name' => 'Expense Worker',
        'hire_date' => now()->toDateString(),
        'manager_employee_id' => $managerEmployee->id,
        'reimbursement_approver_user_id' => $managerUser->id,
        'timezone' => 'UTC',
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    actingAs($managerUser)
        ->post(route('company.hr.reimbursements.categories.store'), [
            'name' => 'Travel',
            'code' => 'TRAVEL',
            'default_expense_account_reference' => 'EXP-TRAVEL',
            'requires_receipt' => false,
            'is_project_rebillable' => false,
        ])
        ->assertRedirect(route('company.hr.reimbursements.index'));

    $categoryId = (string) App\Modules\Hr\Models\HrReimbursementCategory::query()
        ->where('company_id', $company->id)
        ->value('id');

    actingAs($employeeUser)
        ->post(route('company.hr.reimbursements.claims.store'), [
            'employee_id' => '',
            'currency_id' => '',
            'project_id' => '',
            'notes' => 'Travel reimbursement',
            'action' => 'submit',
            'lines' => [[
                'category_id' => $categoryId,
                'expense_date' => now()->toDateString(),
                'description' => 'Airport taxi',
                'amount' => 150,
                'tax_amount' => 0,
                'project_id' => '',
            ]],
        ])
        ->assertRedirect(route('company.hr.reimbursements.index'));

    $claim = HrReimbursementClaim::query()
        ->where('employee_id', $employee->id)
        ->latest('created_at')
        ->first();

    expect($claim)->not->toBeNull();
    expect($claim?->status)->toBe(HrReimbursementClaim::STATUS_SUBMITTED);

    $approvalRequest = ApprovalRequest::query()
        ->where('company_id', $company->id)
        ->where('module', ApprovalRequest::MODULE_HR)
        ->where('action', ApprovalRequest::ACTION_HR_REIMBURSEMENT_APPROVAL)
        ->where('source_id', $claim?->id)
        ->first();

    expect($approvalRequest)->not->toBeNull();
    expect($approvalRequest?->status)->toBe(ApprovalRequest::STATUS_PENDING);

    actingAs($managerUser)
        ->post(route('company.hr.reimbursements.claims.approve', $claim))
        ->assertRedirect();

    expect($claim?->fresh()?->status)->toBe(HrReimbursementClaim::STATUS_MANAGER_APPROVED);
    expect($approvalRequest?->fresh()?->status)->toBe(ApprovalRequest::STATUS_PENDING);

    actingAs($payrollUser)
        ->post(route('company.hr.reimbursements.claims.approve', $claim))
        ->assertRedirect();

    expect($claim?->fresh()?->status)->toBe(HrReimbursementClaim::STATUS_FINANCE_APPROVED);
    expect($approvalRequest?->fresh()?->status)->toBe(ApprovalRequest::STATUS_APPROVED);

    actingAs($owner)
        ->post(route('company.hr.reimbursements.claims.post', $claim))
        ->assertRedirect();

    $claim = $claim?->fresh(['accountingInvoice']);

    expect($claim?->status)->toBe(HrReimbursementClaim::STATUS_POSTED);
    expect($claim?->accountingInvoice)->not->toBeNull();
    expect($claim?->accountingInvoice?->document_type)->toBe(AccountingInvoice::TYPE_VENDOR_BILL);
    expect($claim?->accountingInvoice?->status)->toBe(AccountingInvoice::STATUS_POSTED);

    actingAs($owner)
        ->post(route('company.hr.reimbursements.claims.pay', $claim))
        ->assertRedirect();

    $claim = $claim?->fresh(['accountingPayment']);

    expect($claim?->status)->toBe(HrReimbursementClaim::STATUS_PAID);
    expect($claim?->accountingPayment)->not->toBeNull();
    expect($claim?->accountingPayment?->status)->toBe(AccountingPayment::STATUS_RECONCILED);
});

test('receipt-required reimbursement claim can upload receipt before submission', function () {
    $this->seed(CoreRolesSeeder::class);
    config()->set('core.attachments.scan.enabled', false);
    Storage::fake((string) config('core.attachments.disk', 'local'));

    [$owner, $company] = makeActiveCompanyMember();
    assignHrReimbursementRole($owner, $company->id, 'owner');

    $employeeUser = User::factory()->create();
    $company->users()->syncWithoutDetaching([
        $employeeUser->id => ['role_id' => null, 'is_owner' => false],
    ]);
    assignHrReimbursementRole($employeeUser, $company->id, 'employee_self_service');

    HrEmployee::create([
        'company_id' => $company->id,
        'user_id' => $employeeUser->id,
        'employee_number' => 'EMP-RMB-300',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'first_name' => 'Receipt',
        'last_name' => 'Worker',
        'display_name' => 'Receipt Worker',
        'hire_date' => now()->toDateString(),
        'reimbursement_approver_user_id' => $owner->id,
        'timezone' => 'UTC',
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    actingAs($owner)
        ->post(route('company.hr.reimbursements.categories.store'), [
            'name' => 'Meals',
            'code' => 'MEALS',
            'default_expense_account_reference' => 'EXP-MEALS',
            'requires_receipt' => true,
            'is_project_rebillable' => false,
        ])
        ->assertRedirect(route('company.hr.reimbursements.index'));

    $categoryId = (string) App\Modules\Hr\Models\HrReimbursementCategory::query()
        ->where('company_id', $company->id)
        ->value('id');

    actingAs($employeeUser)
        ->post(route('company.hr.reimbursements.claims.store'), [
            'employee_id' => '',
            'currency_id' => '',
            'project_id' => '',
            'notes' => 'Meal reimbursement',
            'action' => 'draft',
            'lines' => [[
                'category_id' => $categoryId,
                'expense_date' => now()->toDateString(),
                'description' => 'Client lunch',
                'amount' => 45,
                'tax_amount' => 0,
                'project_id' => '',
            ]],
        ])
        ->assertRedirect();

    $claim = HrReimbursementClaim::query()->latest('created_at')->firstOrFail();
    $line = $claim->lines()->firstOrFail();

    actingAs($employeeUser)
        ->post(route('company.hr.reimbursements.receipts.store', $line), [
            'file' => UploadedFile::fake()->create('meal-receipt.pdf', 64, 'application/pdf'),
        ])
        ->assertRedirect();

    expect($line->fresh()?->receiptAttachment)->not->toBeNull();

    actingAs($employeeUser)
        ->post(route('company.hr.reimbursements.claims.submit', $claim))
        ->assertRedirect();

    expect($claim->fresh()?->status)->toBe(HrReimbursementClaim::STATUS_SUBMITTED);
});

test('member without reimbursement permissions cannot open reimbursement workspace', function () {
    $this->seed(CoreRolesSeeder::class);

    [$member, $company] = makeActiveCompanyMember();
    assignHrReimbursementRole($member, $company->id, 'member');

    actingAs($member)
        ->get(route('company.hr.reimbursements.index'))
        ->assertForbidden();
});
