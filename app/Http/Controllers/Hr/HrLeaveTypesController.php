<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrLeaveTypeStoreRequest;
use App\Http\Requests\Hr\HrLeaveTypeUpdateRequest;
use App\Modules\Hr\HrLeaveService;
use App\Modules\Hr\Models\HrLeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HrLeaveTypesController extends Controller
{
    public function create(): Response
    {
        $this->authorize('create', HrLeaveType::class);

        return Inertia::render('hr/leave/types/create', [
            'units' => HrLeaveType::UNITS,
            'form' => [
                'name' => '',
                'code' => '',
                'unit' => HrLeaveType::UNIT_DAYS,
                'requires_allocation' => true,
                'is_paid' => true,
                'requires_approval' => true,
                'allow_negative_balance' => false,
                'max_consecutive_days' => '',
                'color' => '#2563eb',
            ],
        ]);
    }

    public function store(HrLeaveTypeStoreRequest $request, HrLeaveService $leaveService): RedirectResponse
    {
        $this->authorize('create', HrLeaveType::class);

        try {
            $leaveService->createType($request->validated(), $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('company.hr.leave.index')->with('success', 'Leave type created.');
    }

    public function edit(HrLeaveType $leaveType): Response
    {
        $this->authorize('update', $leaveType);

        return Inertia::render('hr/leave/types/edit', [
            'units' => HrLeaveType::UNITS,
            'leaveType' => [
                'id' => $leaveType->id,
                'name' => $leaveType->name,
                'code' => $leaveType->code ?? '',
                'unit' => $leaveType->unit,
                'requires_allocation' => (bool) $leaveType->requires_allocation,
                'is_paid' => (bool) $leaveType->is_paid,
                'requires_approval' => (bool) $leaveType->requires_approval,
                'allow_negative_balance' => (bool) $leaveType->allow_negative_balance,
                'max_consecutive_days' => $leaveType->max_consecutive_days !== null ? (string) $leaveType->max_consecutive_days : '',
                'color' => $leaveType->color ?? '',
            ],
        ]);
    }

    public function update(HrLeaveTypeUpdateRequest $request, HrLeaveType $leaveType, HrLeaveService $leaveService): RedirectResponse
    {
        $this->authorize('update', $leaveType);

        try {
            $leaveService->updateType($leaveType, $request->validated(), $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('company.hr.leave.index')->with('success', 'Leave type updated.');
    }
}
