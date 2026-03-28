<?php

use App\Core\Attachments\Models\Attachment;
use App\Core\MasterData\Models\Currency;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrReimbursementCategory;
use App\Modules\Hr\Models\HrReimbursementClaim;
use App\Modules\Hr\Models\HrReimbursementClaimLine;
use Illuminate\Support\Facades\Schema;

test('hr reimbursement foundation tables exist and reimbursement relations persist', function () {
    foreach ([
        'hr_reimbursement_categories',
        'hr_reimbursement_claims',
        'hr_reimbursement_claim_lines',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    [$user, $company] = makeActiveCompanyMember();

    $employee = HrEmployee::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'employee_number' => 'EMP-RMB-001',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'first_name' => 'Eden',
        'last_name' => 'Worker',
        'display_name' => 'Eden Worker',
        'hire_date' => now()->toDateString(),
        'timezone' => 'UTC',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $currency = Currency::create([
        'company_id' => $company->id,
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $category = HrReimbursementCategory::create([
        'company_id' => $company->id,
        'name' => 'Travel',
        'code' => 'TRAVEL',
        'requires_receipt' => true,
        'is_project_rebillable' => false,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $claim = HrReimbursementClaim::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'currency_id' => $currency->id,
        'claim_number' => 'RMB-0001',
        'status' => HrReimbursementClaim::STATUS_DRAFT,
        'total_amount' => 125.50,
        'requested_by_user_id' => $user->id,
        'notes' => 'Taxi receipts',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $attachment = Attachment::create([
        'company_id' => $company->id,
        'attachable_type' => HrReimbursementClaimLine::class,
        'attachable_id' => $claim->id,
        'security_context' => 'hr_reimbursement_receipt',
        'disk' => 'local',
        'path' => 'attachments/receipt.pdf',
        'file_name' => 'receipt.pdf',
        'original_name' => 'receipt.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size' => 2048,
        'checksum' => 'checksum',
        'scan_status' => Attachment::SCAN_CLEAN,
        'uploaded_by' => $user->id,
    ]);

    $line = HrReimbursementClaimLine::create([
        'company_id' => $company->id,
        'claim_id' => $claim->id,
        'category_id' => $category->id,
        'expense_date' => now()->toDateString(),
        'description' => 'Airport taxi',
        'amount' => 120,
        'tax_amount' => 5.50,
        'receipt_attachment_id' => $attachment->id,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    expect($employee->reimbursementClaims()->count())->toBe(1);
    expect($claim->employee?->is($employee))->toBeTrue();
    expect($claim->currency?->is($currency))->toBeTrue();
    expect($claim->lines()->count())->toBe(1);
    expect($line->category?->is($category))->toBeTrue();
    expect($line->receiptAttachment?->is($attachment))->toBeTrue();
});
