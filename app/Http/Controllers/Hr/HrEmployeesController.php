<?php

namespace App\Http\Controllers\Hr;

use App\Core\Attachments\Models\Attachment;
use App\Core\MasterData\Models\Currency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrEmployeeStoreRequest;
use App\Http\Requests\Hr\HrEmployeeUpdateRequest;
use App\Models\User;
use App\Modules\Hr\HrEmployeeService;
use App\Modules\Hr\HrWorkspaceService;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrEmployeeContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HrEmployeesController extends Controller
{
    public function index(Request $request, HrWorkspaceService $workspaceService): Response
    {
        $this->authorize('viewAny', HrEmployee::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
            'department_id' => ['nullable', 'uuid'],
        ]);

        $employees = HrEmployee::query()
            ->with(['department:id,name', 'designation:id,name', 'managerEmployee:id,display_name', 'user:id,name,email'])
            ->accessibleTo($user)
            ->when(filled($filters['search'] ?? null), function ($query) use ($filters): void {
                $search = trim((string) ($filters['search'] ?? ''));

                $query->where(function ($nested) use ($search): void {
                    $nested
                        ->where('employee_number', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%")
                        ->orWhere('work_email', 'like', "%{$search}%");
                });
            })
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('employment_status', (string) $filters['status']))
            ->when(filled($filters['department_id'] ?? null), fn ($query) => $query->where('department_id', (string) $filters['department_id']))
            ->orderBy('display_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('hr/employees/index', [
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'department_id' => (string) ($filters['department_id'] ?? ''),
            ],
            'statuses' => HrEmployee::STATUSES,
            'departments' => $workspaceService->departmentOptions(),
            'employees' => $employees->through(fn (HrEmployee $employee) => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'display_name' => $employee->display_name,
                'work_email' => $employee->work_email,
                'employment_status' => $employee->employment_status,
                'employment_type' => $employee->employment_type,
                'department_name' => $employee->department?->name,
                'designation_name' => $employee->designation?->name,
                'manager_name' => $employee->managerEmployee?->display_name,
                'linked_user_name' => $employee->user?->name,
                'hire_date' => $employee->hire_date?->toDateString(),
                'can_view' => $user->can('view', $employee),
                'can_edit' => $user->can('update', $employee),
            ]),
            'abilities' => [
                'can_create_employee' => $user->can('create', HrEmployee::class),
            ],
        ]);
    }

    public function create(Request $request, HrWorkspaceService $workspaceService): Response
    {
        $this->authorize('create', HrEmployee::class);

        return Inertia::render('hr/employees/create', [
            'employee' => $this->employeeFormDefaults($request),
            'statuses' => HrEmployee::STATUSES,
            'employmentTypes' => HrEmployee::TYPES,
            'departments' => $workspaceService->departmentOptions(),
            'designations' => $workspaceService->designationOptions(),
            'managers' => $workspaceService->managerOptions(),
            'companyUsers' => $workspaceService->companyUserOptions($request->user()?->current_company_id),
            'accessRoles' => $workspaceService->accessRoleOptions($request->user()?->current_company_id),
        ]);
    }

    public function store(HrEmployeeStoreRequest $request, HrEmployeeService $employeeService): RedirectResponse
    {
        $this->authorize('create', HrEmployee::class);

        $employee = $employeeService->create($request->validated(), $request->user());

        return redirect()
            ->route('company.hr.employees.show', $employee)
            ->with('success', 'Employee created.');
    }

    public function show(HrEmployee $employee, Request $request, HrWorkspaceService $workspaceService): Response
    {
        $this->authorize('view', $employee);

        $user = $request->user();
        $canViewPrivate = $user?->can('viewPrivate', $employee) ?? false;
        $canManagePrivate = $user?->hasPermission('hr.employees.private_manage') ?? false;

        $employee->load([
            'department:id,name,code',
            'designation:id,name,code',
            'systemRole:id,name,slug',
            'managerEmployee:id,display_name,employee_number',
            'user:id,name,email',
            'invite:id,email,employee_id,company_role_id,accepted_at,expires_at,delivery_status,delivery_attempts,last_delivery_at,last_delivery_error',
            'attendanceApprover:id,name,email',
            'leaveApprover:id,name,email',
            'reimbursementApprover:id,name,email',
            'contracts.currency:id,code,name,symbol',
            'documents.attachment:id,original_name,mime_type,size,created_at',
        ]);

        $attachments = Attachment::query()
            ->where('attachable_type', HrEmployee::class)
            ->where('attachable_id', $employee->id)
            ->count();

        return Inertia::render('hr/employees/show', [
            'employee' => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'display_name' => $employee->display_name,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'employment_status' => $employee->employment_status,
                'employment_type' => $employee->employment_type,
                'work_email' => $employee->work_email,
                'personal_email' => $canViewPrivate ? $employee->personal_email : null,
                'work_phone' => $employee->work_phone,
                'personal_phone' => $canViewPrivate ? $employee->personal_phone : null,
                'date_of_birth' => $canViewPrivate ? $employee->date_of_birth?->toDateString() : null,
                'hire_date' => $employee->hire_date?->toDateString(),
                'termination_date' => $employee->termination_date?->toDateString(),
                'timezone' => $employee->timezone,
                'country_code' => $employee->country_code,
                'work_location' => $employee->work_location,
                'bank_account_reference' => $canViewPrivate ? $employee->bank_account_reference : null,
                'emergency_contact_name' => $canViewPrivate ? $employee->emergency_contact_name : null,
                'emergency_contact_phone' => $canViewPrivate ? $employee->emergency_contact_phone : null,
                'notes' => $canViewPrivate ? $employee->notes : null,
                'department' => $employee->department ? ['id' => $employee->department->id, 'name' => $employee->department->name, 'code' => $employee->department->code] : null,
                'designation' => $employee->designation ? ['id' => $employee->designation->id, 'name' => $employee->designation->name, 'code' => $employee->designation->code] : null,
                'manager' => $employee->managerEmployee ? ['id' => $employee->managerEmployee->id, 'display_name' => $employee->managerEmployee->display_name, 'employee_number' => $employee->managerEmployee->employee_number] : null,
                'requires_system_access' => $employee->requires_system_access,
                'system_access_status' => $employee->system_access_status,
                'login_email' => $employee->login_email,
                'system_role' => $employee->systemRole ? ['id' => $employee->systemRole->id, 'name' => $employee->systemRole->name, 'slug' => $employee->systemRole->slug] : null,
                'linked_user' => $employee->user ? ['id' => $employee->user->id, 'name' => $employee->user->name, 'email' => $employee->user->email] : null,
                'invite' => $employee->invite ? [
                    'id' => $employee->invite->id,
                    'email' => $employee->invite->email,
                    'accepted_at' => $employee->invite->accepted_at?->toIso8601String(),
                    'expires_at' => $employee->invite->expires_at?->toIso8601String(),
                    'delivery_status' => $employee->invite->delivery_status,
                    'delivery_attempts' => (int) $employee->invite->delivery_attempts,
                    'last_delivery_at' => $employee->invite->last_delivery_at?->toIso8601String(),
                    'last_delivery_error' => $employee->invite->last_delivery_error,
                ] : null,
                'attendance_approver' => $employee->attendanceApprover ? ['id' => $employee->attendanceApprover->id, 'name' => $employee->attendanceApprover->name, 'email' => $employee->attendanceApprover->email] : null,
                'leave_approver' => $employee->leaveApprover ? ['id' => $employee->leaveApprover->id, 'name' => $employee->leaveApprover->name, 'email' => $employee->leaveApprover->email] : null,
                'reimbursement_approver' => $employee->reimbursementApprover ? ['id' => $employee->reimbursementApprover->id, 'name' => $employee->reimbursementApprover->name, 'email' => $employee->reimbursementApprover->email] : null,
                'contracts' => $employee->contracts->map(fn ($contract) => [
                    'id' => $contract->id,
                    'contract_number' => $contract->contract_number,
                    'status' => $contract->status,
                    'start_date' => $contract->start_date?->toDateString(),
                    'end_date' => $contract->end_date?->toDateString(),
                    'pay_frequency' => $contract->pay_frequency,
                    'salary_basis' => $contract->salary_basis,
                    'base_salary_amount' => $contract->base_salary_amount !== null ? (float) $contract->base_salary_amount : null,
                    'hourly_rate' => $contract->hourly_rate !== null ? (float) $contract->hourly_rate : null,
                    'currency_id' => $contract->currency_id,
                    'currency_code' => $contract->currency?->code,
                    'working_days_per_week' => $contract->working_days_per_week,
                    'standard_hours_per_day' => (float) $contract->standard_hours_per_day,
                    'is_payroll_eligible' => $contract->is_payroll_eligible,
                    'notes' => $contract->notes,
                ])->values()->all(),
                'documents' => $employee->documents->map(fn ($document) => [
                    'id' => $document->id,
                    'document_type' => $document->document_type,
                    'document_name' => $document->document_name,
                    'is_private' => $document->is_private,
                    'valid_until' => $document->valid_until?->toDateString(),
                    'attachment_id' => $document->attachment_id,
                    'original_name' => $document->attachment?->original_name,
                    'mime_type' => $document->attachment?->mime_type,
                    'size' => $document->attachment?->size,
                    'download_url' => route('company.hr.documents.download', $document),
                ])->values()->all(),
                'attachment_count' => $attachments,
            ],
            'abilities' => [
                'can_edit_employee' => $user?->can('update', $employee) ?? false,
                'can_view_private' => $canViewPrivate,
                'can_manage_private' => $canManagePrivate,
                'can_manage_access' => $user?->hasPermission('hr.employee_access.manage') ?? false,
            ],
            'contractStatuses' => HrEmployeeContract::STATUSES,
            'payFrequencies' => HrEmployeeContract::PAY_FREQUENCIES,
            'salaryBases' => HrEmployeeContract::SALARY_BASES,
            'currencies' => Currency::query()->orderBy('code')->get(['id', 'code', 'name'])->map(fn ($currency) => [
                'id' => $currency->id,
                'code' => $currency->code,
                'name' => $currency->name,
            ])->values()->all(),
            'employeeFormOptions' => [
                'departments' => $workspaceService->departmentOptions(),
                'designations' => $workspaceService->designationOptions(),
                'managers' => array_values(array_filter($workspaceService->managerOptions(), fn (array $manager) => $manager['id'] !== $employee->id)),
                'companyUsers' => $workspaceService->companyUserOptions($request->user()?->current_company_id),
                'accessRoles' => $workspaceService->accessRoleOptions($request->user()?->current_company_id),
            ],
            'statuses' => HrEmployee::STATUSES,
            'employmentTypes' => HrEmployee::TYPES,
        ]);
    }

    public function edit(HrEmployee $employee, Request $request, HrWorkspaceService $workspaceService): Response
    {
        $this->authorize('update', $employee);

        return Inertia::render('hr/employees/edit', [
            'employee' => [
                'user_id' => $employee->user_id ?? '',
                'requires_system_access' => $employee->requires_system_access,
                'department_id' => $employee->department_id ?? '',
                'department_name' => '',
                'designation_id' => $employee->designation_id ?? '',
                'designation_name' => '',
                'system_role_id' => $employee->system_role_id ?? '',
                'employee_number' => $employee->employee_number,
                'employment_status' => $employee->employment_status,
                'employment_type' => $employee->employment_type,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'work_email' => $employee->work_email ?? '',
                'login_email' => $employee->login_email ?? '',
                'personal_email' => $employee->personal_email ?? '',
                'work_phone' => $employee->work_phone ?? '',
                'personal_phone' => $employee->personal_phone ?? '',
                'date_of_birth' => $employee->date_of_birth?->toDateString() ?? '',
                'hire_date' => $employee->hire_date?->toDateString() ?? '',
                'termination_date' => $employee->termination_date?->toDateString() ?? '',
                'manager_employee_id' => $employee->manager_employee_id ?? '',
                'attendance_approver_user_id' => $employee->attendance_approver_user_id ?? '',
                'leave_approver_user_id' => $employee->leave_approver_user_id ?? '',
                'reimbursement_approver_user_id' => $employee->reimbursement_approver_user_id ?? '',
                'timezone' => $employee->timezone,
                'country_code' => $employee->country_code ?? '',
                'work_location' => $employee->work_location ?? '',
                'bank_account_reference' => $employee->bank_account_reference ?? '',
                'emergency_contact_name' => $employee->emergency_contact_name ?? '',
                'emergency_contact_phone' => $employee->emergency_contact_phone ?? '',
                'notes' => $employee->notes ?? '',
            ],
            'employeeId' => $employee->id,
            'statuses' => HrEmployee::STATUSES,
            'employmentTypes' => HrEmployee::TYPES,
            'departments' => $workspaceService->departmentOptions(),
            'designations' => $workspaceService->designationOptions(),
            'managers' => array_values(array_filter($workspaceService->managerOptions(), fn (array $manager) => $manager['id'] !== $employee->id)),
            'companyUsers' => $workspaceService->companyUserOptions($request->user()?->current_company_id),
            'accessRoles' => $workspaceService->accessRoleOptions($request->user()?->current_company_id),
        ]);
    }

    public function update(HrEmployeeUpdateRequest $request, HrEmployee $employee, HrEmployeeService $employeeService): RedirectResponse
    {
        $this->authorize('update', $employee);

        $employeeService->update($employee, $request->validated(), $request->user());

        return redirect()
            ->route('company.hr.employees.show', $employee)
            ->with('success', 'Employee updated.');
    }

    public function destroy(HrEmployee $employee): RedirectResponse
    {
        $this->authorize('delete', $employee);

        $employee->delete();

        return redirect()
            ->route('company.hr.employees.index')
            ->with('success', 'Employee deleted.');
    }

    private function employeeFormDefaults(Request $request): array
    {
        return [
            'user_id' => '',
            'requires_system_access' => false,
            'department_id' => '',
            'department_name' => '',
            'designation_id' => '',
            'designation_name' => '',
            'system_role_id' => '',
            'employee_number' => '',
            'employment_status' => HrEmployee::STATUS_DRAFT,
            'employment_type' => HrEmployee::TYPE_FULL_TIME,
            'first_name' => '',
            'last_name' => '',
            'work_email' => '',
            'login_email' => '',
            'personal_email' => '',
            'work_phone' => '',
            'personal_phone' => '',
            'date_of_birth' => '',
            'hire_date' => now()->toDateString(),
            'termination_date' => '',
            'manager_employee_id' => '',
            'attendance_approver_user_id' => '',
            'leave_approver_user_id' => '',
            'reimbursement_approver_user_id' => '',
            'timezone' => $request->user()?->timezone ?? 'UTC',
            'country_code' => '',
            'work_location' => '',
            'bank_account_reference' => '',
            'emergency_contact_name' => '',
            'emergency_contact_phone' => '',
            'notes' => '',
        ];
    }
}
