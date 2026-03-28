<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Hr\HrPayrollWorkspaceService;
use App\Modules\Hr\Models\HrCompensationAssignment;
use App\Modules\Hr\Models\HrPayrollPeriod;
use App\Modules\Hr\Models\HrPayrollRun;
use App\Modules\Hr\Models\HrPayslip;
use App\Modules\Hr\Models\HrSalaryStructure;
use Inertia\Inertia;
use Inertia\Response;

class HrPayrollController extends Controller
{
    public function index(HrPayrollWorkspaceService $workspaceService): Response
    {
        $this->authorize('viewAny', HrPayslip::class);

        $user = request()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $structures = $user->can('viewAny', HrSalaryStructure::class)
            ? HrSalaryStructure::query()->with('lines:id,salary_structure_id')->orderBy('name')->get()
            : collect();
        $assignments = $user->can('viewAny', HrCompensationAssignment::class)
            ? HrCompensationAssignment::query()
                ->with(['employee:id,display_name,employee_number', 'salaryStructure:id,name', 'currency:id,code'])
                ->orderByDesc('effective_from')
                ->limit(20)
                ->get()
            : collect();
        $periods = $user->can('viewAny', HrPayrollPeriod::class)
            ? HrPayrollPeriod::query()->orderByDesc('start_date')->limit(12)->get()
            : collect();
        $runs = $user->can('viewAny', HrPayrollRun::class)
            ? HrPayrollRun::query()
                ->with(['payrollPeriod:id,name,start_date,end_date,payment_date', 'approver:id,name', 'preparedBy:id,name', 'approvedBy:id,name', 'accountingManualJournal:id,entry_number,status'])
                ->latest('created_at')
                ->limit(12)
                ->get()
            : collect();

        $payslips = HrPayslip::query()
            ->with(['employee:id,display_name,employee_number,user_id', 'payrollPeriod:id,name,payment_date', 'payrollRun:id,run_number,status'])
            ->accessibleTo($user)
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('hr/payroll/index', [
            'summary' => $workspaceService->summary($user),
            'structures' => $structures->map(fn (HrSalaryStructure $structure) => [
                'id' => $structure->id,
                'name' => $structure->name,
                'code' => $structure->code,
                'is_active' => (bool) $structure->is_active,
                'line_count' => $structure->lines->count(),
                'can_edit' => $user->can('update', $structure),
            ])->values()->all(),
            'assignments' => $assignments->map(fn (HrCompensationAssignment $assignment) => [
                'id' => $assignment->id,
                'employee_name' => $assignment->employee?->display_name,
                'employee_number' => $assignment->employee?->employee_number,
                'salary_structure_name' => $assignment->salaryStructure?->name,
                'currency_code' => $assignment->currency?->code,
                'pay_frequency' => $assignment->pay_frequency,
                'salary_basis' => $assignment->salary_basis,
                'base_salary_amount' => $assignment->base_salary_amount !== null ? (float) $assignment->base_salary_amount : null,
                'hourly_rate' => $assignment->hourly_rate !== null ? (float) $assignment->hourly_rate : null,
                'effective_from' => $assignment->effective_from?->toDateString(),
                'effective_to' => $assignment->effective_to?->toDateString(),
                'is_active' => (bool) $assignment->is_active,
                'can_edit' => $user->can('update', $assignment),
            ])->values()->all(),
            'periods' => $periods->map(fn (HrPayrollPeriod $period) => [
                'id' => $period->id,
                'name' => $period->name,
                'pay_frequency' => $period->pay_frequency,
                'start_date' => $period->start_date?->toDateString(),
                'end_date' => $period->end_date?->toDateString(),
                'payment_date' => $period->payment_date?->toDateString(),
                'status' => $period->status,
                'can_edit' => $user->can('update', $period),
            ])->values()->all(),
            'runs' => $runs->map(fn (HrPayrollRun $run) => [
                'id' => $run->id,
                'run_number' => $run->run_number,
                'status' => $run->status,
                'period_name' => $run->payrollPeriod?->name,
                'payment_date' => $run->payrollPeriod?->payment_date?->toDateString(),
                'approver_name' => $run->approver?->name,
                'prepared_by_name' => $run->preparedBy?->name,
                'approved_by_name' => $run->approvedBy?->name,
                'entry_number' => $run->accountingManualJournal?->entry_number,
                'entry_status' => $run->accountingManualJournal?->status,
                'total_gross' => (float) $run->total_gross,
                'total_deductions' => (float) $run->total_deductions,
                'total_reimbursements' => (float) $run->total_reimbursements,
                'total_net' => (float) $run->total_net,
                'can_view' => $user->can('view', $run),
            ])->values()->all(),
            'payslips' => $payslips->through(fn (HrPayslip $payslip) => [
                'id' => $payslip->id,
                'payslip_number' => $payslip->payslip_number,
                'status' => $payslip->status,
                'employee_name' => $payslip->employee?->display_name,
                'employee_number' => $payslip->employee?->employee_number,
                'period_name' => $payslip->payrollPeriod?->name,
                'run_number' => $payslip->payrollRun?->run_number,
                'gross_pay' => (float) $payslip->gross_pay,
                'total_deductions' => (float) $payslip->total_deductions,
                'reimbursement_amount' => (float) $payslip->reimbursement_amount,
                'net_pay' => (float) $payslip->net_pay,
                'published_at' => $payslip->published_at?->toIso8601String(),
                'can_view' => $user->can('view', $payslip),
            ]),
            'abilities' => [
                'can_manage_payroll' => $user->hasPermission('hr.payroll.manage'),
                'can_approve_payroll' => $user->hasPermission('hr.payroll.approve'),
                'can_post_payroll' => $user->hasPermission('hr.payroll.post'),
            ],
        ]);
    }
}
