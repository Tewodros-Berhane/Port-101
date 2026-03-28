<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrCompensationAssignmentStoreRequest;
use App\Http\Requests\Hr\HrCompensationAssignmentUpdateRequest;
use App\Models\User;
use App\Modules\Hr\HrPayrollService;
use App\Modules\Hr\HrPayrollWorkspaceService;
use App\Modules\Hr\Models\HrCompensationAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HrCompensationAssignmentsController extends Controller
{
    public function create(Request $request, HrPayrollWorkspaceService $payrollWorkspaceService): Response
    {
        $this->authorize('create', HrCompensationAssignment::class);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return Inertia::render('hr/payroll/assignments/create', [
            'employeeOptions' => $payrollWorkspaceService->employeeOptions($user),
            'contractOptions' => $payrollWorkspaceService->contractOptions($user),
            'salaryStructureOptions' => $payrollWorkspaceService->salaryStructureOptions(),
            'currencyOptions' => $payrollWorkspaceService->currencyOptions((string) $user->current_company_id),
            'form' => [
                'employee_id' => '',
                'contract_id' => '',
                'salary_structure_id' => '',
                'currency_id' => '',
                'effective_from' => now()->toDateString(),
                'effective_to' => '',
                'pay_frequency' => 'monthly',
                'salary_basis' => 'fixed',
                'base_salary_amount' => '',
                'hourly_rate' => '',
                'payroll_group' => '',
                'is_active' => true,
                'notes' => '',
            ],
        ]);
    }

    public function store(HrCompensationAssignmentStoreRequest $request, HrPayrollService $payrollService): RedirectResponse
    {
        $this->authorize('create', HrCompensationAssignment::class);

        $payrollService->createCompensationAssignment($request->validated(), $request->user());

        return redirect()->route('company.hr.payroll.index')->with('success', 'Compensation assignment created.');
    }

    public function edit(Request $request, HrCompensationAssignment $assignment, HrPayrollWorkspaceService $payrollWorkspaceService): Response
    {
        $this->authorize('update', $assignment);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return Inertia::render('hr/payroll/assignments/edit', [
            'employeeOptions' => $payrollWorkspaceService->employeeOptions($user),
            'contractOptions' => $payrollWorkspaceService->contractOptions($user),
            'salaryStructureOptions' => $payrollWorkspaceService->salaryStructureOptions(),
            'currencyOptions' => $payrollWorkspaceService->currencyOptions((string) $user->current_company_id),
            'assignment' => [
                'id' => $assignment->id,
                'employee_id' => $assignment->employee_id,
                'contract_id' => $assignment->contract_id ?? '',
                'salary_structure_id' => $assignment->salary_structure_id ?? '',
                'currency_id' => $assignment->currency_id ?? '',
                'effective_from' => $assignment->effective_from?->toDateString(),
                'effective_to' => $assignment->effective_to?->toDateString(),
                'pay_frequency' => $assignment->pay_frequency,
                'salary_basis' => $assignment->salary_basis,
                'base_salary_amount' => $assignment->base_salary_amount !== null ? (string) $assignment->base_salary_amount : '',
                'hourly_rate' => $assignment->hourly_rate !== null ? (string) $assignment->hourly_rate : '',
                'payroll_group' => $assignment->payroll_group ?? '',
                'is_active' => (bool) $assignment->is_active,
                'notes' => $assignment->notes ?? '',
            ],
        ]);
    }

    public function update(HrCompensationAssignmentUpdateRequest $request, HrCompensationAssignment $assignment, HrPayrollService $payrollService): RedirectResponse
    {
        $this->authorize('update', $assignment);

        $payrollService->updateCompensationAssignment($assignment, $request->validated(), $request->user());

        return redirect()->route('company.hr.payroll.index')->with('success', 'Compensation assignment updated.');
    }
}
