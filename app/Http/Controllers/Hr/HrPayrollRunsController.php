<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrPayrollDecisionRequest;
use App\Http\Requests\Hr\HrPayrollRunStoreRequest;
use App\Models\User;
use App\Modules\Approvals\ApprovalQueueService;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Hr\HrPayrollService;
use App\Modules\Hr\HrPayrollWorkspaceService;
use App\Modules\Hr\Models\HrPayrollRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HrPayrollRunsController extends Controller
{
    public function create(Request $request, HrPayrollWorkspaceService $payrollWorkspaceService): Response
    {
        $this->authorize('create', HrPayrollRun::class);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return Inertia::render('hr/payroll/runs/create', [
            'periodOptions' => $payrollWorkspaceService->payrollPeriodOptions(),
            'approverOptions' => $payrollWorkspaceService->companyUserOptions((string) $user->current_company_id),
            'form' => [
                'payroll_period_id' => '',
                'approver_user_id' => '',
            ],
        ]);
    }

    public function store(HrPayrollRunStoreRequest $request, HrPayrollService $payrollService): RedirectResponse
    {
        $this->authorize('create', HrPayrollRun::class);

        $run = $payrollService->createPayrollRun($request->validated(), $request->user());

        return redirect()->route('company.hr.payroll.runs.show', $run)->with('success', 'Payroll run created.');
    }

    public function show(Request $request, HrPayrollRun $run): Response
    {
        $this->authorize('view', $run);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $run->loadMissing([
            'payrollPeriod',
            'approver:id,name',
            'preparedBy:id,name',
            'approvedBy:id,name',
            'postedBy:id,name',
            'accountingManualJournal:id,entry_number,status',
            'workEntries.employee:id,display_name,employee_number',
            'payslips.employee:id,display_name,employee_number,user_id',
            'payslips.lines',
        ]);

        $approvalRequest = ApprovalRequest::query()
            ->where('company_id', $run->company_id)
            ->where('source_type', HrPayrollRun::class)
            ->where('source_id', $run->id)
            ->first();

        return Inertia::render('hr/payroll/runs/show', [
            'run' => [
                'id' => $run->id,
                'run_number' => $run->run_number,
                'status' => $run->status,
                'approver_user_id' => $run->approver_user_id,
                'approver_name' => $run->approver?->name,
                'prepared_by_name' => $run->preparedBy?->name,
                'approved_by_name' => $run->approvedBy?->name,
                'posted_by_name' => $run->postedBy?->name,
                'decision_notes' => $run->decision_notes,
                'total_gross' => (float) $run->total_gross,
                'total_deductions' => (float) $run->total_deductions,
                'total_reimbursements' => (float) $run->total_reimbursements,
                'total_net' => (float) $run->total_net,
                'prepared_at' => $run->prepared_at?->toIso8601String(),
                'approved_at' => $run->approved_at?->toIso8601String(),
                'posted_at' => $run->posted_at?->toIso8601String(),
                'period' => [
                    'id' => $run->payrollPeriod?->id,
                    'name' => $run->payrollPeriod?->name,
                    'pay_frequency' => $run->payrollPeriod?->pay_frequency,
                    'start_date' => $run->payrollPeriod?->start_date?->toDateString(),
                    'end_date' => $run->payrollPeriod?->end_date?->toDateString(),
                    'payment_date' => $run->payrollPeriod?->payment_date?->toDateString(),
                ],
                'journal_entry_number' => $run->accountingManualJournal?->entry_number,
                'journal_entry_status' => $run->accountingManualJournal?->status,
            ],
            'workEntries' => $run->workEntries->map(fn ($entry) => [
                'id' => $entry->id,
                'employee_name' => $entry->employee?->display_name,
                'employee_number' => $entry->employee?->employee_number,
                'entry_type' => $entry->entry_type,
                'quantity' => (float) $entry->quantity,
                'amount_reference' => $entry->amount_reference !== null ? (float) $entry->amount_reference : null,
                'status' => $entry->status,
                'conflict_reason' => $entry->conflict_reason,
            ])->values()->all(),
            'payslips' => $run->payslips->map(fn ($payslip) => [
                'id' => $payslip->id,
                'payslip_number' => $payslip->payslip_number,
                'status' => $payslip->status,
                'employee_name' => $payslip->employee?->display_name,
                'employee_number' => $payslip->employee?->employee_number,
                'gross_pay' => (float) $payslip->gross_pay,
                'total_deductions' => (float) $payslip->total_deductions,
                'reimbursement_amount' => (float) $payslip->reimbursement_amount,
                'net_pay' => (float) $payslip->net_pay,
                'line_count' => $payslip->lines->count(),
                'can_view' => $user->can('view', $payslip),
            ])->values()->all(),
            'approvalRequest' => $approvalRequest ? [
                'id' => $approvalRequest->id,
                'status' => $approvalRequest->status,
            ] : null,
            'abilities' => [
                'can_prepare' => $user->can('prepare', $run),
                'can_approve' => $user->can('approve', $run),
                'can_reject' => $user->can('reject', $run),
                'can_post' => $user->can('post', $run),
            ],
        ]);
    }

    public function prepare(Request $request, HrPayrollRun $run, HrPayrollService $payrollService, ApprovalQueueService $approvalQueueService): RedirectResponse
    {
        $this->authorize('prepare', $run);

        $payrollService->prepareRun($run, $request->user());
        $approvalQueueService->syncPendingRequests($request->user()->currentCompany, $request->user()->id);

        return back()->with('success', 'Payroll run prepared.');
    }

    public function approve(Request $request, HrPayrollRun $run, ApprovalQueueService $approvalQueueService): RedirectResponse
    {
        $this->authorize('approve', $run);

        $approvalRequest = ApprovalRequest::query()
            ->where('company_id', $run->company_id)
            ->where('source_type', HrPayrollRun::class)
            ->where('source_id', $run->id)
            ->firstOrFail();

        $approvalQueueService->approve($approvalRequest, $request->user());

        return back()->with('success', 'Payroll run approved.');
    }

    public function reject(HrPayrollDecisionRequest $request, HrPayrollRun $run, ApprovalQueueService $approvalQueueService): RedirectResponse
    {
        $this->authorize('reject', $run);

        $approvalRequest = ApprovalRequest::query()
            ->where('company_id', $run->company_id)
            ->where('source_type', HrPayrollRun::class)
            ->where('source_id', $run->id)
            ->firstOrFail();

        $approvalQueueService->reject($approvalRequest, $request->user(), $request->validated('reason'));

        return back()->with('success', 'Payroll run rejected.');
    }

    public function post(Request $request, HrPayrollRun $run, HrPayrollService $payrollService): RedirectResponse
    {
        $this->authorize('post', $run);

        $payrollService->postRun($run, $request->user());

        return back()->with('success', 'Payroll run posted.');
    }
}
