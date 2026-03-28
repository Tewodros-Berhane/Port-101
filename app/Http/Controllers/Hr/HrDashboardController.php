<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Hr\HrWorkspaceService;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrEmployeeContract;
use App\Modules\Hr\Models\HrPayslip;
use Inertia\Inertia;
use Inertia\Response;

class HrDashboardController extends Controller
{
    public function index(HrWorkspaceService $workspaceService): Response
    {
        $this->authorize('viewAny', HrEmployee::class);

        $user = request()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $recentEmployees = HrEmployee::query()
            ->with(['department:id,name', 'designation:id,name', 'user:id,name'])
            ->accessibleTo($user)
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (HrEmployee $employee) => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'display_name' => $employee->display_name,
                'employment_status' => $employee->employment_status,
                'employment_type' => $employee->employment_type,
                'department_name' => $employee->department?->name,
                'designation_name' => $employee->designation?->name,
                'linked_user_name' => $employee->user?->name,
                'hire_date' => $employee->hire_date?->toDateString(),
                'can_view' => $user->can('view', $employee),
            ])
            ->values()
            ->all();

        $contractsEndingSoon = HrEmployeeContract::query()
            ->with('employee:id,display_name,employee_number')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays(60)->toDateString()])
            ->orderBy('end_date')
            ->limit(6)
            ->get()
            ->filter(fn (HrEmployeeContract $contract) => $user->can('view', $contract))
            ->map(fn (HrEmployeeContract $contract) => [
                'id' => $contract->id,
                'employee_id' => $contract->employee_id,
                'employee_name' => $contract->employee?->display_name,
                'employee_number' => $contract->employee?->employee_number,
                'contract_number' => $contract->contract_number,
                'status' => $contract->status,
                'end_date' => $contract->end_date?->toDateString(),
            ])
            ->values()
            ->all();

        return Inertia::render('hr/index', [
            'summary' => $workspaceService->summary($user),
            'recentEmployees' => $recentEmployees,
            'contractsEndingSoon' => $contractsEndingSoon,
            'abilities' => [
                'can_create_employee' => $user->can('create', HrEmployee::class),
                'can_view_payroll' => $user->can('viewAny', HrPayslip::class),
                'can_view_reports' => $user->hasPermission('hr.reports.view'),
            ],
        ]);
    }
}
