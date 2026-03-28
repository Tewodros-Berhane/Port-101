<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrReimbursementClaimStoreRequest;
use App\Http\Requests\Hr\HrReimbursementClaimUpdateRequest;
use App\Http\Requests\Hr\HrReimbursementDecisionRequest;
use App\Models\User;
use App\Modules\Approvals\ApprovalQueueService;
use App\Modules\Hr\HrReimbursementService;
use App\Modules\Hr\HrReimbursementWorkspaceService;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrReimbursementClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HrReimbursementClaimsController extends Controller
{
    public function create(Request $request, HrReimbursementWorkspaceService $workspaceService): Response
    {
        $this->authorize('create', HrReimbursementClaim::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $linkedEmployeeId = HrEmployee::query()
            ->where('company_id', $user->current_company_id)
            ->where('user_id', $user->id)
            ->value('id');

        $currencies = $workspaceService->currencyOptions((string) $user->current_company_id);
        $defaultCurrency = collect($currencies)->firstWhere('code', $user->currentCompany?->currency_code);
        $defaultCurrencyId = $defaultCurrency['id'] ?? ($currencies[0]['id'] ?? '');

        return Inertia::render('hr/reimbursements/claims/create', [
            'employeeOptions' => $workspaceService->employeeOptions($user),
            'categoryOptions' => $workspaceService->categoryOptions(),
            'projectOptions' => $workspaceService->projectOptions($user),
            'currencyOptions' => $currencies,
            'form' => [
                'employee_id' => $linkedEmployeeId ?? '',
                'currency_id' => $defaultCurrencyId,
                'project_id' => '',
                'notes' => '',
                'action' => 'draft',
                'lines' => [[
                    'id' => '',
                    'category_id' => '',
                    'expense_date' => now()->toDateString(),
                    'description' => '',
                    'amount' => '',
                    'tax_amount' => '0',
                    'project_id' => '',
                ]],
            ],
        ]);
    }

    public function store(
        HrReimbursementClaimStoreRequest $request,
        HrReimbursementService $service,
        ApprovalQueueService $approvalQueueService,
    ): RedirectResponse {
        $this->authorize('create', HrReimbursementClaim::class);

        try {
            $claim = $service->createClaim($request->validated(), $request->user());
            $approvalQueueService->syncPendingRequests($request->user()->currentCompany, $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        if ($claim->status === HrReimbursementClaim::STATUS_DRAFT) {
            return redirect()
                ->route('company.hr.reimbursements.claims.edit', $claim)
                ->with('success', sprintf('Reimbursement claim %s saved as draft.', $claim->claim_number));
        }

        return redirect()
            ->route('company.hr.reimbursements.index')
            ->with('success', sprintf('Reimbursement claim %s submitted.', $claim->claim_number));
    }

    public function edit(Request $request, HrReimbursementClaim $claim, HrReimbursementWorkspaceService $workspaceService): Response
    {
        $this->authorize('update', $claim);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $claim->loadMissing([
            'lines.category:id,name,requires_receipt,is_project_rebillable',
            'lines.receiptAttachment:id,original_name,mime_type,size,scan_status',
        ]);

        return Inertia::render('hr/reimbursements/claims/edit', [
            'employeeOptions' => $workspaceService->employeeOptions($user),
            'categoryOptions' => $workspaceService->categoryOptions(),
            'projectOptions' => $workspaceService->projectOptions($user),
            'currencyOptions' => $workspaceService->currencyOptions((string) $user->current_company_id),
            'claim' => [
                'id' => $claim->id,
                'claim_number' => $claim->claim_number,
                'status' => $claim->status,
                'employee_id' => $claim->employee_id,
                'currency_id' => $claim->currency_id ?? '',
                'project_id' => $claim->project_id ?? '',
                'notes' => $claim->notes ?? '',
                'decision_notes' => $claim->decision_notes,
                'lines' => $claim->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'category_id' => $line->category_id,
                    'expense_date' => $line->expense_date?->toDateString(),
                    'description' => $line->description,
                    'amount' => (string) $line->amount,
                    'tax_amount' => (string) $line->tax_amount,
                    'project_id' => $line->project_id ?? '',
                    'category_name' => $line->category?->name,
                    'requires_receipt' => (bool) ($line->category?->requires_receipt ?? false),
                    'receipt_attachment' => $line->receiptAttachment ? [
                        'id' => $line->receiptAttachment->id,
                        'original_name' => $line->receiptAttachment->original_name,
                        'mime_type' => $line->receiptAttachment->mime_type,
                        'size' => $line->receiptAttachment->size,
                    ] : null,
                ])->values()->all(),
            ],
        ]);
    }

    public function update(
        HrReimbursementClaimUpdateRequest $request,
        HrReimbursementClaim $claim,
        HrReimbursementService $service,
        ApprovalQueueService $approvalQueueService,
    ): RedirectResponse {
        $this->authorize('update', $claim);

        try {
            $claim = $service->updateClaim($claim, $request->validated(), $request->user());
            $approvalQueueService->syncPendingRequests($request->user()->currentCompany, $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        if ($claim->status === HrReimbursementClaim::STATUS_DRAFT) {
            return redirect()
                ->route('company.hr.reimbursements.claims.edit', $claim)
                ->with('success', 'Reimbursement claim updated.');
        }

        return redirect()
            ->route('company.hr.reimbursements.index')
            ->with('success', 'Reimbursement claim submitted.');
    }

    public function submit(Request $request, HrReimbursementClaim $claim, HrReimbursementService $service, ApprovalQueueService $approvalQueueService): RedirectResponse
    {
        $this->authorize('submit', $claim);

        try {
            $service->submit($claim, $request->user()?->id);
            $approvalQueueService->syncPendingRequests($request->user()?->currentCompany, $request->user()?->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Reimbursement claim submitted.');
    }

    public function approve(Request $request, HrReimbursementClaim $claim, ApprovalQueueService $approvalQueueService): RedirectResponse
    {
        $this->authorize('approve', $claim);

        try {
            $approvalQueueService->approve($this->approvalRequestForClaim($claim), $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Reimbursement claim approved.');
    }

    public function reject(HrReimbursementDecisionRequest $request, HrReimbursementClaim $claim, ApprovalQueueService $approvalQueueService): RedirectResponse
    {
        $this->authorize('reject', $claim);

        try {
            $approvalQueueService->reject(
                $this->approvalRequestForClaim($claim),
                $request->user(),
                $request->validated('reason'),
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Reimbursement claim rejected.');
    }

    public function postToAccounting(Request $request, HrReimbursementClaim $claim, HrReimbursementService $service, ApprovalQueueService $approvalQueueService): RedirectResponse
    {
        $this->authorize('postToAccounting', $claim);

        try {
            $service->postToAccounting($claim, $request->user());
            $approvalQueueService->syncPendingRequests($request->user()->currentCompany, $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Accounting vendor bill posted for reimbursement claim.');
    }

    public function recordPayment(Request $request, HrReimbursementClaim $claim, HrReimbursementService $service): RedirectResponse
    {
        $this->authorize('recordPayment', $claim);

        try {
            $service->recordPayment($claim, $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Reimbursement claim marked as paid.');
    }

    private function approvalRequestForClaim(HrReimbursementClaim $claim): \App\Modules\Approvals\Models\ApprovalRequest
    {
        return \App\Modules\Approvals\Models\ApprovalRequest::query()
            ->where('company_id', $claim->company_id)
            ->where('source_type', HrReimbursementClaim::class)
            ->where('source_id', $claim->id)
            ->firstOrFail();
    }
}
