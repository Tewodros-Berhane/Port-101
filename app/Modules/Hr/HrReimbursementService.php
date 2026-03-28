<?php

namespace App\Modules\Hr;

use App\Core\Attachments\AttachmentUploadService;
use App\Core\Attachments\Models\Attachment;
use App\Core\Company\Models\Company;
use App\Core\Company\Models\CompanyUser;
use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Models\User;
use App\Modules\Accounting\AccountingInvoiceWorkflowService;
use App\Modules\Accounting\AccountingPaymentWorkflowService;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrReimbursementCategory;
use App\Modules\Hr\Models\HrReimbursementClaim;
use App\Modules\Hr\Models\HrReimbursementClaimLine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HrReimbursementService
{
    public function __construct(
        private readonly HrReimbursementNotificationService $notificationService,
        private readonly AccountingInvoiceWorkflowService $accountingInvoiceWorkflowService,
        private readonly AccountingPaymentWorkflowService $accountingPaymentWorkflowService,
        private readonly AttachmentUploadService $attachmentUploadService,
    ) {}

    public function createCategory(array $attributes, User $actor): HrReimbursementCategory
    {
        return HrReimbursementCategory::create([
            ...$attributes,
            'company_id' => $actor->current_company_id,
            'code' => $this->resolveCode(
                HrReimbursementCategory::class,
                (string) $actor->current_company_id,
                $attributes['code'] ?? null,
                $attributes['name'] ?? null,
            ),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    public function updateCategory(
        HrReimbursementCategory $category,
        array $attributes,
        User $actor
    ): HrReimbursementCategory {
        $category->update([
            ...$attributes,
            'code' => $this->resolveCode(
                HrReimbursementCategory::class,
                (string) $category->company_id,
                $attributes['code'] ?? null,
                $attributes['name'] ?? null,
                (string) $category->code,
            ),
            'updated_by' => $actor->id,
        ]);

        return $category->fresh() ?? $category;
    }

    public function createClaim(array $attributes, User $actor): HrReimbursementClaim
    {
        return DB::transaction(function () use ($attributes, $actor): HrReimbursementClaim {
            $employee = $this->resolveEmployee($attributes['employee_id'] ?? null, $actor);
            $claim = HrReimbursementClaim::create([
                ...$this->baseClaimPayload($attributes, $employee, $actor),
                'claim_number' => $this->resolveClaimNumber((string) $employee->company_id),
                'status' => HrReimbursementClaim::STATUS_DRAFT,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncLines($claim, $attributes['lines'] ?? [], $actor->id);

            if (($attributes['action'] ?? 'submit') === 'submit') {
                return $this->submit($claim, $actor->id);
            }

            return $claim->fresh(['lines.category', 'currency', 'employee']) ?? $claim;
        });
    }

    public function updateClaim(
        HrReimbursementClaim $claim,
        array $attributes,
        User $actor
    ): HrReimbursementClaim {
        return DB::transaction(function () use ($claim, $attributes, $actor): HrReimbursementClaim {
            $claim = HrReimbursementClaim::query()
                ->with('employee.department')
                ->lockForUpdate()
                ->findOrFail($claim->id);

            if (! in_array($claim->status, [
                HrReimbursementClaim::STATUS_DRAFT,
                HrReimbursementClaim::STATUS_REJECTED,
            ], true)) {
                throw ValidationException::withMessages([
                    'claim' => 'Only draft or rejected reimbursement claims can be updated.',
                ]);
            }

            $employee = $this->resolveEmployee(
                $attributes['employee_id'] ?? (string) $claim->employee_id,
                $actor
            );

            $claim->update([
                ...$this->baseClaimPayload($attributes, $employee, $actor, $claim),
                'status' => HrReimbursementClaim::STATUS_DRAFT,
                'decision_notes' => null,
                'submitted_at' => null,
                'manager_approved_at' => null,
                'finance_approved_at' => null,
                'approved_at' => null,
                'approved_by_user_id' => null,
                'manager_approved_by_user_id' => null,
                'finance_approved_by_user_id' => null,
                'rejected_by_user_id' => null,
                'rejected_at' => null,
                'accounting_invoice_id' => null,
                'accounting_payment_id' => null,
                'updated_by' => $actor->id,
            ]);

            $this->syncLines($claim, $attributes['lines'] ?? [], $actor->id);

            if (($attributes['action'] ?? 'save') === 'submit') {
                return $this->submit($claim->fresh() ?? $claim, $actor->id);
            }

            return $claim->fresh(['lines.category', 'currency', 'employee']) ?? $claim;
        });
    }

    public function submit(HrReimbursementClaim $claim, ?string $actorId = null): HrReimbursementClaim
    {
        return DB::transaction(function () use ($claim, $actorId): HrReimbursementClaim {
            $claim = HrReimbursementClaim::query()
                ->with(['employee.department', 'lines.category', 'currency'])
                ->lockForUpdate()
                ->findOrFail($claim->id);

            if (! in_array($claim->status, [
                HrReimbursementClaim::STATUS_DRAFT,
                HrReimbursementClaim::STATUS_REJECTED,
            ], true)) {
                throw ValidationException::withMessages([
                    'claim' => 'Only draft or rejected reimbursement claims can be submitted.',
                ]);
            }

            if ($claim->lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => 'At least one reimbursement line is required.',
                ]);
            }

            $managerApproverId = $claim->manager_approver_user_id
                ?: $this->resolveManagerApprover($claim->employee);
            $financeApproverId = $claim->finance_approver_user_id
                ?: $this->resolveFinanceApprover(
                    companyId: (string) $claim->company_id,
                    excludingUserId: $managerApproverId,
                );

            if (! $managerApproverId && ! $financeApproverId) {
                throw ValidationException::withMessages([
                    'claim' => 'No reimbursement approver is configured for this employee.',
                ]);
            }

            if (! $managerApproverId) {
                $managerApproverId = $financeApproverId;
                $financeApproverId = null;
            }

            $this->ensureReceiptRequirementsSatisfied($claim);

            $claim->update([
                'status' => HrReimbursementClaim::STATUS_SUBMITTED,
                'approver_user_id' => $managerApproverId,
                'manager_approver_user_id' => $managerApproverId,
                'finance_approver_user_id' => $financeApproverId,
                'decision_notes' => null,
                'submitted_at' => now(),
                'manager_approved_at' => null,
                'finance_approved_at' => null,
                'approved_at' => null,
                'approved_by_user_id' => null,
                'manager_approved_by_user_id' => null,
                'finance_approved_by_user_id' => null,
                'rejected_by_user_id' => null,
                'rejected_at' => null,
                'updated_by' => $actorId,
            ]);

            $claim = $claim->fresh(['employee', 'approver']) ?? $claim;
            $this->notificationService->notifySubmitted($claim, $actorId);

            return $claim;
        });
    }

    public function approve(HrReimbursementClaim $claim, ?string $actorId = null): HrReimbursementClaim
    {
        return DB::transaction(function () use ($claim, $actorId): HrReimbursementClaim {
            $claim = HrReimbursementClaim::query()
                ->with(['employee.department', 'lines.category', 'currency'])
                ->lockForUpdate()
                ->findOrFail($claim->id);

            if (! in_array($claim->status, [
                HrReimbursementClaim::STATUS_SUBMITTED,
                HrReimbursementClaim::STATUS_MANAGER_APPROVED,
            ], true)) {
                throw ValidationException::withMessages([
                    'claim' => 'Only submitted or manager-approved reimbursement claims can be approved.',
                ]);
            }

            if ($claim->status === HrReimbursementClaim::STATUS_SUBMITTED) {
                $nextFinanceApproverId = $claim->finance_approver_user_id;

                if ($nextFinanceApproverId && (string) $nextFinanceApproverId !== (string) $actorId) {
                    $claim->update([
                        'status' => HrReimbursementClaim::STATUS_MANAGER_APPROVED,
                        'approver_user_id' => $nextFinanceApproverId,
                        'manager_approved_by_user_id' => $actorId,
                        'manager_approved_at' => now(),
                        'decision_notes' => null,
                        'updated_by' => $actorId,
                    ]);

                    return $claim->fresh(['employee', 'approver']) ?? $claim;
                }

                $claim->update([
                    'manager_approved_by_user_id' => $actorId,
                    'manager_approved_at' => now(),
                ]);
            }

            $claim->update([
                'status' => HrReimbursementClaim::STATUS_FINANCE_APPROVED,
                'approver_user_id' => null,
                'finance_approved_by_user_id' => $actorId,
                'finance_approved_at' => now(),
                'approved_by_user_id' => $actorId,
                'approved_at' => now(),
                'decision_notes' => null,
                'updated_by' => $actorId,
            ]);

            $claim = $claim->fresh(['employee']) ?? $claim;
            $this->notificationService->notifyDecision($claim, 'approved', $actorId);

            return $claim;
        });
    }

    public function reject(
        HrReimbursementClaim $claim,
        ?string $reason = null,
        ?string $actorId = null
    ): HrReimbursementClaim {
        return DB::transaction(function () use ($claim, $reason, $actorId): HrReimbursementClaim {
            $claim = HrReimbursementClaim::query()
                ->with('employee')
                ->lockForUpdate()
                ->findOrFail($claim->id);

            if (! in_array($claim->status, [
                HrReimbursementClaim::STATUS_SUBMITTED,
                HrReimbursementClaim::STATUS_MANAGER_APPROVED,
            ], true)) {
                throw ValidationException::withMessages([
                    'claim' => 'Only submitted or manager-approved reimbursement claims can be rejected.',
                ]);
            }

            $claim->update([
                'status' => HrReimbursementClaim::STATUS_REJECTED,
                'approver_user_id' => null,
                'decision_notes' => $reason,
                'rejected_by_user_id' => $actorId,
                'rejected_at' => now(),
                'updated_by' => $actorId,
            ]);

            $claim = $claim->fresh(['employee']) ?? $claim;
            $this->notificationService->notifyDecision($claim, 'rejected', $actorId);

            return $claim;
        });
    }

    public function postToAccounting(
        HrReimbursementClaim $claim,
        User $actor
    ): HrReimbursementClaim {
        return DB::transaction(function () use ($claim, $actor): HrReimbursementClaim {
            $claim = HrReimbursementClaim::query()
                ->with(['employee', 'currency', 'lines.category', 'accountingInvoice'])
                ->lockForUpdate()
                ->findOrFail($claim->id);

            if (! in_array($claim->status, [
                HrReimbursementClaim::STATUS_FINANCE_APPROVED,
                HrReimbursementClaim::STATUS_POSTED,
            ], true)) {
                throw ValidationException::withMessages([
                    'claim' => 'Only finance-approved reimbursement claims can be handed off to Accounting.',
                ]);
            }

            if ($claim->status === HrReimbursementClaim::STATUS_POSTED && $claim->accounting_invoice_id) {
                return $claim;
            }

            if ($claim->lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'claim' => 'Reimbursement claims require at least one line before accounting handoff.',
                ]);
            }

            $partner = $this->resolveReimbursementPartner($claim->employee, $actor->id);
            $linePayload = $claim->lines
                ->map(fn (HrReimbursementClaimLine $line) => [
                    'product_id' => null,
                    'description' => trim(($line->category?->name ? $line->category->name.' - ' : '').$line->description),
                    'quantity' => 1,
                    'unit_price' => round((float) $line->amount + (float) $line->tax_amount, 2),
                    'tax_rate' => 0,
                ])
                ->values()
                ->all();

            $invoice = $claim->accountingInvoice;

            if ($invoice && $invoice->status === AccountingInvoice::STATUS_DRAFT) {
                $invoice = $this->accountingInvoiceWorkflowService->updateDraft($invoice, [
                    'partner_id' => $partner->id,
                    'document_type' => AccountingInvoice::TYPE_VENDOR_BILL,
                    'invoice_date' => now()->toDateString(),
                    'due_date' => now()->addDays(7)->toDateString(),
                    'currency_code' => $claim->currency?->code,
                    'notes' => 'Employee reimbursement claim '.$claim->claim_number,
                    'external_reference' => $claim->claim_number,
                    'lines' => $linePayload,
                ], $actor->id);
            } else {
                $invoice = $this->accountingInvoiceWorkflowService->createDraft([
                    'partner_id' => $partner->id,
                    'document_type' => AccountingInvoice::TYPE_VENDOR_BILL,
                    'invoice_date' => now()->toDateString(),
                    'due_date' => now()->addDays(7)->toDateString(),
                    'currency_code' => $claim->currency?->code,
                    'notes' => 'Employee reimbursement claim '.$claim->claim_number,
                    'external_reference' => $claim->claim_number,
                    'lines' => $linePayload,
                ], (string) $claim->company_id, $actor->id);
            }

            $invoice = $this->accountingInvoiceWorkflowService->post($invoice, $actor->id);

            $claim->update([
                'status' => HrReimbursementClaim::STATUS_POSTED,
                'accounting_invoice_id' => $invoice->id,
                'updated_by' => $actor->id,
            ]);

            return $claim->fresh(['employee', 'accountingInvoice']) ?? $claim;
        });
    }

    public function recordPayment(HrReimbursementClaim $claim, User $actor): HrReimbursementClaim
    {
        return DB::transaction(function () use ($claim, $actor): HrReimbursementClaim {
            $claim = HrReimbursementClaim::query()
                ->with('accountingInvoice')
                ->lockForUpdate()
                ->findOrFail($claim->id);

            if (! in_array($claim->status, [
                HrReimbursementClaim::STATUS_POSTED,
                HrReimbursementClaim::STATUS_PAID,
            ], true)) {
                throw ValidationException::withMessages([
                    'claim' => 'Only posted reimbursement claims can be marked paid.',
                ]);
            }

            if ($claim->status === HrReimbursementClaim::STATUS_PAID && $claim->accounting_payment_id) {
                return $claim;
            }

            $invoice = $claim->accountingInvoice;

            if (! $invoice) {
                throw ValidationException::withMessages([
                    'claim' => 'An accounting invoice is required before payment can be recorded.',
                ]);
            }

            $amount = round((float) $invoice->balance_due, 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'claim' => 'This reimbursement invoice has no outstanding balance.',
                ]);
            }

            $payment = $this->accountingPaymentWorkflowService->createDraft([
                'invoice_id' => (string) $invoice->id,
                'payment_date' => now()->toDateString(),
                'amount' => $amount,
                'method' => 'reimbursement',
                'reference' => $claim->claim_number,
                'notes' => 'Employee reimbursement payout for '.$claim->claim_number,
                'external_reference' => $claim->claim_number,
            ], (string) $claim->company_id, $actor->id);

            $payment = $this->accountingPaymentWorkflowService->post($payment, $actor->id);
            $payment = $this->accountingPaymentWorkflowService->reconcile($payment, $actor->id);

            $claim->update([
                'status' => HrReimbursementClaim::STATUS_PAID,
                'accounting_payment_id' => $payment->id,
                'updated_by' => $actor->id,
            ]);

            $claim = $claim->fresh(['employee', 'accountingInvoice', 'accountingPayment']) ?? $claim;
            $this->notificationService->notifyPaid($claim, $actor->id);

            return $claim;
        });
    }

    public function uploadReceipt(
        HrReimbursementClaimLine $line,
        UploadedFile $file,
        User $actor
    ): HrReimbursementClaimLine {
        return DB::transaction(function () use ($line, $file, $actor): HrReimbursementClaimLine {
            $line = HrReimbursementClaimLine::query()
                ->with(['claim', 'receiptAttachment'])
                ->lockForUpdate()
                ->findOrFail($line->id);

            $claim = $line->claim;

            if (! $claim || ! in_array($claim->status, [
                HrReimbursementClaim::STATUS_DRAFT,
                HrReimbursementClaim::STATUS_REJECTED,
            ], true)) {
                throw ValidationException::withMessages([
                    'receipt' => 'Receipts can only be changed while the claim is draft or rejected.',
                ]);
            }

            if ($line->receiptAttachment) {
                $this->deleteAttachment($line->receiptAttachment);
            }

            $attachment = $this->attachmentUploadService->store(
                file: $file,
                attachable: $line,
                companyId: (string) $line->company_id,
                context: 'hr_reimbursement_receipt',
                actorId: $actor->id,
            );

            $line->update([
                'receipt_attachment_id' => $attachment->id,
                'updated_by' => $actor->id,
            ]);

            return $line->fresh(['receiptAttachment']) ?? $line;
        });
    }

    public function removeReceipt(HrReimbursementClaimLine $line, ?string $actorId = null): HrReimbursementClaimLine
    {
        return DB::transaction(function () use ($line, $actorId): HrReimbursementClaimLine {
            $line = HrReimbursementClaimLine::query()
                ->with(['claim', 'receiptAttachment'])
                ->lockForUpdate()
                ->findOrFail($line->id);

            $claim = $line->claim;

            if (! $claim || ! in_array($claim->status, [
                HrReimbursementClaim::STATUS_DRAFT,
                HrReimbursementClaim::STATUS_REJECTED,
            ], true)) {
                throw ValidationException::withMessages([
                    'receipt' => 'Receipts can only be removed while the claim is draft or rejected.',
                ]);
            }

            if ($line->receiptAttachment) {
                $this->deleteAttachment($line->receiptAttachment);
            }

            $line->update([
                'receipt_attachment_id' => null,
                'updated_by' => $actorId,
            ]);

            return $line->fresh(['receiptAttachment']) ?? $line;
        });
    }

    private function syncLines(
        HrReimbursementClaim $claim,
        array $lines,
        ?string $actorId = null
    ): void {
        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'At least one reimbursement line is required.',
            ]);
        }

        $existingLines = $claim->lines()
            ->with('receiptAttachment')
            ->get()
            ->keyBy('id');
        $seen = [];
        $total = 0.0;

        foreach ($lines as $lineIndex => $lineData) {
            $category = HrReimbursementCategory::query()
                ->where('company_id', $claim->company_id)
                ->findOrFail($lineData['category_id']);

            $amount = round((float) ($lineData['amount'] ?? 0), 2);
            $taxAmount = round((float) ($lineData['tax_amount'] ?? 0), 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    "lines.{$lineIndex}.amount" => 'Expense amount must be greater than zero.',
                ]);
            }

            $payload = [
                'company_id' => $claim->company_id,
                'category_id' => $category->id,
                'expense_date' => $lineData['expense_date'],
                'description' => trim((string) $lineData['description']),
                'amount' => $amount,
                'tax_amount' => $taxAmount,
                'project_id' => $lineData['project_id'] ?? null,
                'updated_by' => $actorId,
            ];

            if ($payload['description'] === '') {
                throw ValidationException::withMessages([
                    "lines.{$lineIndex}.description" => 'Description is required for each reimbursement line.',
                ]);
            }

            $lineId = filled($lineData['id'] ?? null)
                ? (string) $lineData['id']
                : null;

            if ($lineId && $existingLines->has($lineId)) {
                $line = $existingLines->get($lineId);
                $line->update($payload);
                $seen[] = $lineId;
            } else {
                $line = $claim->lines()->create([
                    ...$payload,
                    'created_by' => $actorId,
                ]);
                $seen[] = (string) $line->id;
            }

            $total += $amount + $taxAmount;
        }

        $existingLines
            ->reject(fn (HrReimbursementClaimLine $line) => in_array((string) $line->id, $seen, true))
            ->each(function (HrReimbursementClaimLine $line): void {
                if ($line->receiptAttachment) {
                    $this->deleteAttachment($line->receiptAttachment);
                }

                $line->delete();
            });

        $claim->update([
            'total_amount' => round($total, 2),
            'updated_by' => $actorId,
        ]);
    }

    private function baseClaimPayload(
        array $attributes,
        HrEmployee $employee,
        User $actor,
        ?HrReimbursementClaim $claim = null
    ): array {
        $currencyId = $this->resolveCurrencyId((string) $employee->company_id, $attributes['currency_id'] ?? null);
        $managerApproverId = $this->resolveManagerApprover($employee);
        $financeApproverId = $this->resolveFinanceApprover(
            companyId: (string) $employee->company_id,
            excludingUserId: $managerApproverId,
        );

        return [
            ...Arr::only($attributes, ['notes']),
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'currency_id' => $currencyId,
            'requested_by_user_id' => $claim?->requested_by_user_id ?: $actor->id,
            'approver_user_id' => $claim?->approver_user_id,
            'manager_approver_user_id' => $managerApproverId,
            'finance_approver_user_id' => $financeApproverId,
            'project_id' => $attributes['project_id'] ?? null,
        ];
    }

    private function resolveEmployee(?string $employeeId, User $actor): HrEmployee
    {
        $query = HrEmployee::query()
            ->where('company_id', $actor->current_company_id);

        if ($employeeId) {
            $query->whereKey($employeeId);
        } else {
            $query->where('user_id', $actor->id);
        }

        $employee = $query->first();

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee_id' => 'A linked employee record is required for reimbursement activity.',
            ]);
        }

        if ((string) $employee->user_id === (string) $actor->id) {
            return $employee;
        }

        if (
            $actor->hasPermission('hr.reimbursements.manage')
            && HrEmployee::query()->accessibleTo($actor)->whereKey($employee->id)->exists()
        ) {
            return $employee;
        }

        throw ValidationException::withMessages([
            'employee_id' => 'You cannot manage reimbursements for the selected employee.',
        ]);
    }

    private function resolveManagerApprover(?HrEmployee $employee): ?string
    {
        if (! $employee) {
            return null;
        }

        if ($employee->reimbursement_approver_user_id) {
            return (string) $employee->reimbursement_approver_user_id;
        }

        $employee->loadMissing('department');

        if ($employee->department?->reimbursement_approver_user_id) {
            return (string) $employee->department->reimbursement_approver_user_id;
        }

        if (! $employee->manager_employee_id) {
            return null;
        }

        return HrEmployee::query()
            ->whereKey($employee->manager_employee_id)
            ->value('user_id');
    }

    private function resolveFinanceApprover(
        string $companyId,
        ?string $excludingUserId = null
    ): ?string {
        foreach (['payroll_manager', 'hr_manager'] as $roleSlug) {
            $userId = CompanyUser::query()
                ->where('company_id', $companyId)
                ->when($excludingUserId, fn ($query, $id) => $query->where('user_id', '!=', $id))
                ->whereHas('role', fn ($query) => $query->where('slug', $roleSlug))
                ->value('user_id');

            if ($userId) {
                return (string) $userId;
            }
        }

        $ownerId = CompanyUser::query()
            ->where('company_id', $companyId)
            ->when($excludingUserId, fn ($query, $id) => $query->where('user_id', '!=', $id))
            ->where('is_owner', true)
            ->value('user_id');

        return $ownerId ? (string) $ownerId : null;
    }

    private function resolveCurrencyId(string $companyId, ?string $currencyId = null): ?string
    {
        if ($currencyId) {
            return Currency::query()
                ->where('company_id', $companyId)
                ->findOrFail($currencyId)
                ->id;
        }

        $companyCurrencyCode = Company::query()
            ->whereKey($companyId)
            ->value('currency_code');

        if (! $companyCurrencyCode) {
            return null;
        }

        return Currency::query()
            ->where('company_id', $companyId)
            ->where('code', $companyCurrencyCode)
            ->value('id');
    }

    private function ensureReceiptRequirementsSatisfied(HrReimbursementClaim $claim): void
    {
        foreach ($claim->lines as $line) {
            if ($line->category?->requires_receipt && ! $line->receipt_attachment_id) {
                throw ValidationException::withMessages([
                    'lines' => 'Receipts are required for one or more reimbursement lines before submission.',
                ]);
            }
        }
    }

    private function resolveReimbursementPartner(
        HrEmployee $employee,
        ?string $actorId = null
    ): Partner {
        $externalReference = 'hr_reimbursement_employee:'.$employee->id;

        $partner = Partner::query()
            ->where('company_id', $employee->company_id)
            ->where('external_reference', $externalReference)
            ->first();

        if ($partner) {
            return $partner;
        }

        $codeBase = 'EMP-RMB-'.Str::upper(substr((string) ($employee->employee_number ?: $employee->id), -6));
        $code = $codeBase;
        $counter = 2;

        while (Partner::query()->where('company_id', $employee->company_id)->where('code', $code)->exists()) {
            $code = Str::limit($codeBase, 9, '').str_pad((string) $counter, 2, '0', STR_PAD_LEFT);
            $counter++;
        }

        return Partner::create([
            'company_id' => $employee->company_id,
            'external_reference' => $externalReference,
            'code' => $code,
            'name' => $employee->display_name,
            'type' => 'vendor',
            'email' => $employee->work_email ?: $employee->personal_email,
            'phone' => $employee->work_phone ?: $employee->personal_phone,
            'is_active' => true,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    private function deleteAttachment(Attachment $attachment): void
    {
        if (Storage::disk($attachment->disk)->exists($attachment->path)) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }

        $attachment->delete();
    }

    private function resolveClaimNumber(string $companyId): string
    {
        $prefix = 'RMB-';
        $latest = HrReimbursementClaim::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('claim_number', 'like', $prefix.'%')
            ->orderByDesc('claim_number')
            ->value('claim_number');

        $sequence = $latest ? ((int) Str::afterLast((string) $latest, '-')) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function resolveCode(
        string $modelClass,
        string $companyId,
        mixed $proposed,
        mixed $name,
        ?string $current = null
    ): ?string {
        $candidate = trim((string) $proposed);

        if ($candidate !== '') {
            return $candidate;
        }

        if ($current) {
            return $current;
        }

        $base = Str::upper(Str::limit(Str::slug((string) $name, ''), 12, ''));

        if ($base === '') {
            return null;
        }

        $exists = $modelClass::query()
            ->where('company_id', $companyId)
            ->where('code', $base)
            ->exists();

        if (! $exists) {
            return $base;
        }

        for ($index = 2; $index <= 99; $index++) {
            $candidateCode = Str::limit($base, 9, '').str_pad((string) $index, 2, '0', STR_PAD_LEFT);

            $exists = $modelClass::query()
                ->where('company_id', $companyId)
                ->where('code', $candidateCode)
                ->exists();

            if (! $exists) {
                return $candidateCode;
            }
        }

        return $base.Str::upper(Str::random(2));
    }
}
