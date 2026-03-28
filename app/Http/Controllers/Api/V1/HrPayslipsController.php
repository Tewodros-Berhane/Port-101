<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Modules\Hr\Models\HrPayslip;
use App\Modules\Hr\Models\HrPayslipLine;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrPayslipsController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HrPayslip::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $perPage = ApiQuery::perPage($request);
        $status = trim((string) $request->input('status', ''));
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['issued_at', 'created_at', 'payslip_number', 'net_pay', 'gross_pay', 'status'],
            defaultSort: 'issued_at',
            defaultDirection: 'desc',
        );

        $payslips = HrPayslip::query()
            ->with([
                'employee:id,display_name,employee_number,user_id',
                'payrollPeriod:id,name,start_date,end_date,payment_date',
                'currency:id,code,symbol',
            ])
            ->withCount('lines')
            ->accessibleTo($user)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $payslips,
            data: collect($payslips->items())->map(fn (HrPayslip $payslip) => $this->mapPayslip($payslip, $user, false))->all(),
            sort: $sort,
            direction: $direction,
            filters: [
                'status' => $status,
            ],
        );
    }

    public function show(HrPayslip $payslip, Request $request): JsonResponse
    {
        $this->authorize('view', $payslip);

        return $this->respond($this->mapPayslip(
            $payslip->load([
                'employee:id,display_name,employee_number,user_id',
                'payrollRun:id,status',
                'payrollPeriod:id,name,start_date,end_date,payment_date',
                'currency:id,code,symbol',
                'publishedBy:id,name',
                'lines',
            ]),
            $request->user(),
            true,
        ));
    }

    private function mapPayslip(HrPayslip $payslip, ?User $user, bool $includeLines): array
    {
        $payload = [
            'id' => $payslip->id,
            'payslip_number' => $payslip->payslip_number,
            'status' => $payslip->status,
            'employee_id' => $payslip->employee_id,
            'employee_name' => $payslip->employee?->display_name,
            'employee_number' => $payslip->employee?->employee_number,
            'payroll_run_id' => $payslip->payroll_run_id,
            'payroll_run_status' => $payslip->payrollRun?->status,
            'payroll_period_id' => $payslip->payroll_period_id,
            'payroll_period_name' => $payslip->payrollPeriod?->name,
            'payment_date' => $payslip->payrollPeriod?->payment_date?->toDateString(),
            'currency_id' => $payslip->currency_id,
            'currency_code' => $payslip->currency?->code,
            'currency_symbol' => $payslip->currency?->symbol,
            'gross_pay' => (float) $payslip->gross_pay,
            'total_deductions' => (float) $payslip->total_deductions,
            'reimbursement_amount' => (float) $payslip->reimbursement_amount,
            'net_pay' => (float) $payslip->net_pay,
            'issued_at' => $payslip->issued_at?->toIso8601String(),
            'paid_at' => $payslip->paid_at?->toIso8601String(),
            'published_at' => $payslip->published_at?->toIso8601String(),
            'published_by_name' => $payslip->publishedBy?->name,
            'lines_count' => (int) ($payslip->lines_count ?? $payslip->lines()->count()),
            'can_view' => $user?->can('view', $payslip) ?? false,
        ];

        if (! $includeLines) {
            return $payload;
        }

        $payload['lines'] = $payslip->lines->map(fn (HrPayslipLine $line) => [
            'id' => $line->id,
            'line_type' => $line->line_type,
            'code' => $line->code,
            'name' => $line->name,
            'quantity' => (float) $line->quantity,
            'rate' => (float) $line->rate,
            'amount' => (float) $line->amount,
            'source_type' => $line->source_type,
            'source_id' => $line->source_id,
            'line_order' => (int) $line->line_order,
        ])->values()->all();

        return $payload;
    }
}
