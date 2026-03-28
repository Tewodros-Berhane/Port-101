<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrLeaveAllocationStoreRequest;
use App\Http\Requests\Hr\HrLeaveAllocationUpdateRequest;
use App\Models\User;
use App\Modules\Hr\HrLeaveService;
use App\Modules\Hr\HrLeaveWorkspaceService;
use App\Modules\Hr\Models\HrLeaveAllocation;
use App\Modules\Hr\Models\HrLeavePeriod;
use App\Modules\Hr\Models\HrLeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HrLeaveAllocationsController extends Controller
{
    public function create(HrLeaveWorkspaceService $workspaceService): Response
    {
        $this->authorize('create', HrLeaveAllocation::class);
        $user = request()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return Inertia::render('hr/leave/allocations/create', [
            'employeeOptions' => $workspaceService->employeeOptions($user),
            'leaveTypes' => HrLeaveType::query()->orderBy('name')->get(['id', 'name', 'unit']),
            'leavePeriods' => HrLeavePeriod::query()->orderByDesc('start_date')->get(['id', 'name']),
            'form' => [
                'employee_id' => '',
                'leave_type_id' => '',
                'leave_period_id' => '',
                'allocated_amount' => '0',
                'used_amount' => '0',
                'carry_forward_amount' => '0',
                'expires_at' => '',
                'notes' => '',
            ],
        ]);
    }

    public function store(HrLeaveAllocationStoreRequest $request, HrLeaveService $leaveService): RedirectResponse
    {
        $this->authorize('create', HrLeaveAllocation::class);

        try {
            $leaveService->createAllocation($request->validated(), $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('company.hr.leave.index')->with('success', 'Leave allocation created.');
    }

    public function edit(HrLeaveAllocation $allocation, HrLeaveWorkspaceService $workspaceService): Response
    {
        $this->authorize('update', $allocation);
        $user = request()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return Inertia::render('hr/leave/allocations/edit', [
            'employeeOptions' => $workspaceService->employeeOptions($user),
            'leaveTypes' => HrLeaveType::query()->orderBy('name')->get(['id', 'name', 'unit']),
            'leavePeriods' => HrLeavePeriod::query()->orderByDesc('start_date')->get(['id', 'name']),
            'allocation' => [
                'id' => $allocation->id,
                'employee_id' => $allocation->employee_id,
                'leave_type_id' => $allocation->leave_type_id,
                'leave_period_id' => $allocation->leave_period_id,
                'allocated_amount' => (string) $allocation->allocated_amount,
                'used_amount' => (string) $allocation->used_amount,
                'carry_forward_amount' => (string) $allocation->carry_forward_amount,
                'expires_at' => $allocation->expires_at?->toDateString() ?? '',
                'notes' => $allocation->notes ?? '',
            ],
        ]);
    }

    public function update(HrLeaveAllocationUpdateRequest $request, HrLeaveAllocation $allocation, HrLeaveService $leaveService): RedirectResponse
    {
        $this->authorize('update', $allocation);

        try {
            $leaveService->updateAllocation($allocation, $request->validated(), $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('company.hr.leave.index')->with('success', 'Leave allocation updated.');
    }
}
