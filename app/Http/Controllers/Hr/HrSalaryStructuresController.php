<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrSalaryStructureStoreRequest;
use App\Http\Requests\Hr\HrSalaryStructureUpdateRequest;
use App\Modules\Hr\HrPayrollService;
use App\Modules\Hr\Models\HrSalaryStructure;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class HrSalaryStructuresController extends Controller
{
    public function create(): Response
    {
        $this->authorize('create', HrSalaryStructure::class);

        return Inertia::render('hr/payroll/structures/create', [
            'form' => [
                'name' => '',
                'code' => '',
                'is_active' => true,
                'notes' => '',
                'lines' => [[
                    'line_type' => 'earning',
                    'calculation_type' => 'fixed',
                    'code' => 'BASIC_ALLOWANCE',
                    'name' => 'Basic allowance',
                    'amount' => '',
                    'percentage_rate' => '',
                    'is_active' => true,
                ]],
            ],
        ]);
    }

    public function store(HrSalaryStructureStoreRequest $request, HrPayrollService $payrollService): RedirectResponse
    {
        $this->authorize('create', HrSalaryStructure::class);

        $payrollService->createSalaryStructure($request->validated(), $request->user());

        return redirect()->route('company.hr.payroll.index')->with('success', 'Salary structure created.');
    }

    public function edit(HrSalaryStructure $structure): Response
    {
        $this->authorize('update', $structure);
        $structure->loadMissing('lines');

        return Inertia::render('hr/payroll/structures/edit', [
            'structure' => [
                'id' => $structure->id,
                'name' => $structure->name,
                'code' => $structure->code,
                'is_active' => (bool) $structure->is_active,
                'notes' => $structure->notes ?? '',
                'lines' => $structure->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'line_type' => $line->line_type,
                    'calculation_type' => $line->calculation_type,
                    'code' => $line->code,
                    'name' => $line->name,
                    'amount' => $line->amount !== null ? (string) $line->amount : '',
                    'percentage_rate' => $line->percentage_rate !== null ? (string) $line->percentage_rate : '',
                    'is_active' => (bool) $line->is_active,
                ])->values()->all(),
            ],
        ]);
    }

    public function update(HrSalaryStructureUpdateRequest $request, HrSalaryStructure $structure, HrPayrollService $payrollService): RedirectResponse
    {
        $this->authorize('update', $structure);

        $payrollService->updateSalaryStructure($structure, $request->validated(), $request->user());

        return redirect()->route('company.hr.payroll.index')->with('success', 'Salary structure updated.');
    }
}
