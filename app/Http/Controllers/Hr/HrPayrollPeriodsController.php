<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrPayrollPeriodStoreRequest;
use App\Http\Requests\Hr\HrPayrollPeriodUpdateRequest;
use App\Modules\Hr\HrPayrollService;
use App\Modules\Hr\Models\HrPayrollPeriod;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class HrPayrollPeriodsController extends Controller
{
    public function create(): Response
    {
        $this->authorize('create', HrPayrollPeriod::class);

        return Inertia::render('hr/payroll/periods/create', [
            'form' => [
                'name' => '',
                'pay_frequency' => 'monthly',
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
                'payment_date' => now()->endOfMonth()->toDateString(),
                'status' => HrPayrollPeriod::STATUS_OPEN,
            ],
        ]);
    }

    public function store(HrPayrollPeriodStoreRequest $request, HrPayrollService $payrollService): RedirectResponse
    {
        $this->authorize('create', HrPayrollPeriod::class);

        $payrollService->createPayrollPeriod($request->validated(), $request->user());

        return redirect()->route('company.hr.payroll.index')->with('success', 'Payroll period created.');
    }

    public function edit(HrPayrollPeriod $period): Response
    {
        $this->authorize('update', $period);

        return Inertia::render('hr/payroll/periods/edit', [
            'period' => [
                'id' => $period->id,
                'name' => $period->name,
                'pay_frequency' => $period->pay_frequency,
                'start_date' => $period->start_date?->toDateString(),
                'end_date' => $period->end_date?->toDateString(),
                'payment_date' => $period->payment_date?->toDateString(),
                'status' => $period->status,
            ],
        ]);
    }

    public function update(HrPayrollPeriodUpdateRequest $request, HrPayrollPeriod $period, HrPayrollService $payrollService): RedirectResponse
    {
        $this->authorize('update', $period);

        $payrollService->updatePayrollPeriod($period, $request->validated(), $request->user());

        return redirect()->route('company.hr.payroll.index')->with('success', 'Payroll period updated.');
    }
}
