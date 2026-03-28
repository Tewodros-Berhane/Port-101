<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrShiftAssignmentStoreRequest;
use App\Http\Requests\Hr\HrShiftAssignmentUpdateRequest;
use App\Models\User;
use App\Modules\Hr\HrAttendanceService;
use App\Modules\Hr\HrAttendanceWorkspaceService;
use App\Modules\Hr\Models\HrShiftAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HrShiftAssignmentsController extends Controller
{
    public function create(Request $request, HrAttendanceWorkspaceService $workspaceService): Response
    {
        $this->authorize('create', HrShiftAssignment::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return Inertia::render('hr/attendance/assignments/create', [
            'employeeOptions' => $workspaceService->employeeOptions($user),
            'shiftOptions' => $workspaceService->shiftOptions(),
            'form' => [
                'employee_id' => '',
                'shift_id' => '',
                'from_date' => now()->toDateString(),
                'to_date' => '',
            ],
        ]);
    }

    public function store(HrShiftAssignmentStoreRequest $request, HrAttendanceService $attendanceService): RedirectResponse
    {
        $this->authorize('create', HrShiftAssignment::class);

        try {
            $attendanceService->createAssignment($request->validated(), $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('company.hr.attendance.index')->with('success', 'Shift assignment created.');
    }

    public function edit(Request $request, HrShiftAssignment $assignment, HrAttendanceWorkspaceService $workspaceService): Response
    {
        $this->authorize('update', $assignment);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return Inertia::render('hr/attendance/assignments/edit', [
            'employeeOptions' => $workspaceService->employeeOptions($user),
            'shiftOptions' => $workspaceService->shiftOptions(),
            'assignment' => [
                'id' => $assignment->id,
                'employee_id' => $assignment->employee_id,
                'shift_id' => $assignment->shift_id,
                'from_date' => $assignment->from_date?->toDateString(),
                'to_date' => $assignment->to_date?->toDateString() ?? '',
            ],
        ]);
    }

    public function update(HrShiftAssignmentUpdateRequest $request, HrShiftAssignment $assignment, HrAttendanceService $attendanceService): RedirectResponse
    {
        $this->authorize('update', $assignment);

        try {
            $attendanceService->updateAssignment($assignment, $request->validated(), $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('company.hr.attendance.index')->with('success', 'Shift assignment updated.');
    }
}
