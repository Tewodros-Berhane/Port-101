<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Models\HrPayslip;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HrPayslipsController extends Controller
{
    public function show(Request $request, HrPayslip $payslip): Response
    {
        $this->authorize('view', $payslip);

        $payslip->loadMissing([
            'employee:id,display_name,employee_number,user_id',
            'payrollRun:id,run_number,status',
            'payrollPeriod:id,name,start_date,end_date,payment_date',
            'lines',
            'currency:id,code,symbol,name',
            'publishedBy:id,name',
        ]);

        return Inertia::render('hr/payroll/payslips/show', [
            'payslip' => [
                'id' => $payslip->id,
                'payslip_number' => $payslip->payslip_number,
                'status' => $payslip->status,
                'employee_name' => $payslip->employee?->display_name,
                'employee_number' => $payslip->employee?->employee_number,
                'run_number' => $payslip->payrollRun?->run_number,
                'period_name' => $payslip->payrollPeriod?->name,
                'start_date' => $payslip->payrollPeriod?->start_date?->toDateString(),
                'end_date' => $payslip->payrollPeriod?->end_date?->toDateString(),
                'payment_date' => $payslip->payrollPeriod?->payment_date?->toDateString(),
                'gross_pay' => (float) $payslip->gross_pay,
                'total_deductions' => (float) $payslip->total_deductions,
                'reimbursement_amount' => (float) $payslip->reimbursement_amount,
                'net_pay' => (float) $payslip->net_pay,
                'currency_code' => $payslip->currency?->code,
                'currency_symbol' => $payslip->currency?->symbol,
                'published_by_name' => $payslip->publishedBy?->name,
                'published_at' => $payslip->published_at?->toIso8601String(),
                'lines' => $payslip->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'line_type' => $line->line_type,
                    'code' => $line->code,
                    'name' => $line->name,
                    'quantity' => (float) $line->quantity,
                    'rate' => (float) $line->rate,
                    'amount' => (float) $line->amount,
                ])->values()->all(),
            ],
        ]);
    }
}
