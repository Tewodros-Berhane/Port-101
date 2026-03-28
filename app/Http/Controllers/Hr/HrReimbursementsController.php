<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Hr\HrReimbursementWorkspaceService;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrReimbursementCategory;
use App\Modules\Hr\Models\HrReimbursementClaim;
use Inertia\Inertia;
use Inertia\Response;

class HrReimbursementsController extends Controller
{
    public function index(HrReimbursementWorkspaceService $workspaceService): Response
    {
        $this->authorize('viewAny', HrReimbursementClaim::class);

        $user = request()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $filters = request()->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', HrReimbursementClaim::STATUSES)],
            'employee_id' => ['nullable', 'uuid'],
        ]);

        $claims = HrReimbursementClaim::query()
            ->with([
                'employee:id,display_name,employee_number,user_id',
                'approver:id,name',
                'approvedBy:id,name',
                'rejectedBy:id,name',
                'accountingInvoice:id,invoice_number,status',
                'accountingPayment:id,payment_number,status',
                'lines:id,claim_id,category_id,receipt_attachment_id',
                'lines.category:id,name,requires_receipt',
            ])
            ->accessibleTo($user)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['employee_id'] ?? null, fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $categories = HrReimbursementCategory::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'requires_receipt', 'is_project_rebillable', 'default_expense_account_reference']);

        $linkedEmployeeId = HrEmployee::query()
            ->where('company_id', $user->current_company_id)
            ->where('user_id', $user->id)
            ->value('id');

        return Inertia::render('hr/reimbursements/index', [
            'summary' => $workspaceService->summary($user),
            'filters' => [
                'status' => $filters['status'] ?? '',
                'employee_id' => $filters['employee_id'] ?? '',
            ],
            'statuses' => HrReimbursementClaim::STATUSES,
            'employeeOptions' => $workspaceService->employeeOptions($user),
            'linkedEmployeeId' => $linkedEmployeeId,
            'categories' => $categories->map(fn (HrReimbursementCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
                'requires_receipt' => (bool) $category->requires_receipt,
                'is_project_rebillable' => (bool) $category->is_project_rebillable,
                'default_expense_account_reference' => $category->default_expense_account_reference,
                'can_edit' => $user->can('update', $category),
            ])->values()->all(),
            'claims' => $claims->through(fn (HrReimbursementClaim $claim) => [
                'id' => $claim->id,
                'claim_number' => $claim->claim_number,
                'status' => $claim->status,
                'employee_name' => $claim->employee?->display_name,
                'employee_number' => $claim->employee?->employee_number,
                'approver_name' => $claim->approver?->name,
                'approved_by_name' => $claim->approvedBy?->name,
                'rejected_by_name' => $claim->rejectedBy?->name,
                'total_amount' => (float) $claim->total_amount,
                'line_count' => $claim->lines->count(),
                'missing_required_receipts' => $claim->lines
                    ->filter(fn ($line) => (bool) ($line->category?->requires_receipt ?? false) && ! $line->receipt_attachment_id)
                    ->count(),
                'decision_notes' => $claim->decision_notes,
                'invoice_number' => $claim->accountingInvoice?->invoice_number,
                'invoice_status' => $claim->accountingInvoice?->status,
                'payment_number' => $claim->accountingPayment?->payment_number,
                'payment_status' => $claim->accountingPayment?->status,
                'submitted_at' => $claim->submitted_at?->toIso8601String(),
                'approved_at' => $claim->approved_at?->toIso8601String(),
                'rejected_at' => $claim->rejected_at?->toIso8601String(),
                'can_edit' => $user->can('update', $claim),
                'can_submit' => $user->can('submit', $claim),
                'can_approve' => $user->can('approve', $claim),
                'can_reject' => $user->can('reject', $claim),
                'can_post' => $user->can('postToAccounting', $claim),
                'can_pay' => $user->can('recordPayment', $claim),
            ]),
            'abilities' => [
                'can_create_claim' => $user->can('create', HrReimbursementClaim::class),
                'can_manage_categories' => $user->can('create', HrReimbursementCategory::class),
                'can_approve_claims' => $user->hasPermission('hr.reimbursements.approve'),
            ],
        ]);
    }
}
