<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrShiftStoreRequest;
use App\Http\Requests\Hr\HrShiftUpdateRequest;
use App\Modules\Hr\HrAttendanceService;
use App\Modules\Hr\Models\HrShift;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class HrShiftsController extends Controller
{
    public function create(): Response
    {
        $this->authorize('create', HrShift::class);

        return Inertia::render('hr/attendance/shifts/create', [
            'form' => [
                'name' => '',
                'code' => '',
                'start_time' => '09:00',
                'end_time' => '17:00',
                'grace_minutes' => '10',
                'auto_attendance_enabled' => false,
            ],
        ]);
    }

    public function store(HrShiftStoreRequest $request, HrAttendanceService $attendanceService): RedirectResponse
    {
        $this->authorize('create', HrShift::class);

        $attendanceService->createShift($request->validated(), $request->user());

        return redirect()->route('company.hr.attendance.index')->with('success', 'Shift created.');
    }

    public function edit(HrShift $shift): Response
    {
        $this->authorize('update', $shift);

        return Inertia::render('hr/attendance/shifts/edit', [
            'shift' => [
                'id' => $shift->id,
                'name' => $shift->name,
                'code' => $shift->code ?? '',
                'start_time' => substr((string) $shift->start_time, 0, 5),
                'end_time' => substr((string) $shift->end_time, 0, 5),
                'grace_minutes' => (string) $shift->grace_minutes,
                'auto_attendance_enabled' => (bool) $shift->auto_attendance_enabled,
            ],
        ]);
    }

    public function update(HrShiftUpdateRequest $request, HrShift $shift, HrAttendanceService $attendanceService): RedirectResponse
    {
        $this->authorize('update', $shift);

        $attendanceService->updateShift($shift, $request->validated(), $request->user());

        return redirect()->route('company.hr.attendance.index')->with('success', 'Shift updated.');
    }
}
