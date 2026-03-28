<?php

use App\Http\Controllers\Hr\HrAttendanceController;
use App\Http\Controllers\Hr\HrAttendanceRequestsController;
use App\Http\Controllers\Hr\HrDashboardController;
use App\Http\Controllers\Hr\HrEmployeeContractsController;
use App\Http\Controllers\Hr\HrEmployeeDocumentsController;
use App\Http\Controllers\Hr\HrEmployeesController;
use App\Http\Controllers\Hr\HrLeaveAllocationsController;
use App\Http\Controllers\Hr\HrLeavePeriodsController;
use App\Http\Controllers\Hr\HrLeaveRequestsController;
use App\Http\Controllers\Hr\HrLeaveTypesController;
use App\Http\Controllers\Hr\HrShiftAssignmentsController;
use App\Http\Controllers\Hr\HrShiftsController;
use Illuminate\Support\Facades\Route;

Route::get('hr', [HrDashboardController::class, 'index'])
    ->name('modules.hr');

Route::prefix('hr')->name('hr.')->group(function () {
    Route::get('employees', [HrEmployeesController::class, 'index'])
        ->name('employees.index');
    Route::get('employees/create', [HrEmployeesController::class, 'create'])
        ->name('employees.create');
    Route::post('employees', [HrEmployeesController::class, 'store'])
        ->name('employees.store');
    Route::get('employees/{employee}', [HrEmployeesController::class, 'show'])
        ->name('employees.show');
    Route::get('employees/{employee}/edit', [HrEmployeesController::class, 'edit'])
        ->name('employees.edit');
    Route::put('employees/{employee}', [HrEmployeesController::class, 'update'])
        ->name('employees.update');
    Route::delete('employees/{employee}', [HrEmployeesController::class, 'destroy'])
        ->name('employees.destroy');

    Route::post('employees/{employee}/contracts', [HrEmployeeContractsController::class, 'store'])
        ->name('contracts.store');
    Route::put('contracts/{contract}', [HrEmployeeContractsController::class, 'update'])
        ->name('contracts.update');
    Route::delete('contracts/{contract}', [HrEmployeeContractsController::class, 'destroy'])
        ->name('contracts.destroy');

    Route::post('employees/{employee}/documents', [HrEmployeeDocumentsController::class, 'store'])
        ->name('documents.store');
    Route::get('documents/{document}/download', [HrEmployeeDocumentsController::class, 'download'])
        ->name('documents.download');
    Route::delete('documents/{document}', [HrEmployeeDocumentsController::class, 'destroy'])
        ->name('documents.destroy');

    Route::get('leave', [HrLeaveRequestsController::class, 'index'])
        ->name('leave.index');
    Route::get('leave/requests/create', [HrLeaveRequestsController::class, 'create'])
        ->name('leave.requests.create');
    Route::post('leave/requests', [HrLeaveRequestsController::class, 'store'])
        ->name('leave.requests.store');
    Route::get('leave/requests/{leaveRequest}/edit', [HrLeaveRequestsController::class, 'edit'])
        ->name('leave.requests.edit');
    Route::put('leave/requests/{leaveRequest}', [HrLeaveRequestsController::class, 'update'])
        ->name('leave.requests.update');
    Route::post('leave/requests/{leaveRequest}/submit', [HrLeaveRequestsController::class, 'submit'])
        ->name('leave.requests.submit');
    Route::post('leave/requests/{leaveRequest}/approve', [HrLeaveRequestsController::class, 'approve'])
        ->name('leave.requests.approve');
    Route::post('leave/requests/{leaveRequest}/reject', [HrLeaveRequestsController::class, 'reject'])
        ->name('leave.requests.reject');
    Route::post('leave/requests/{leaveRequest}/cancel', [HrLeaveRequestsController::class, 'cancel'])
        ->name('leave.requests.cancel');

    Route::get('leave/types/create', [HrLeaveTypesController::class, 'create'])
        ->name('leave.types.create');
    Route::post('leave/types', [HrLeaveTypesController::class, 'store'])
        ->name('leave.types.store');
    Route::get('leave/types/{leaveType}/edit', [HrLeaveTypesController::class, 'edit'])
        ->name('leave.types.edit');
    Route::put('leave/types/{leaveType}', [HrLeaveTypesController::class, 'update'])
        ->name('leave.types.update');

    Route::get('leave/periods/create', [HrLeavePeriodsController::class, 'create'])
        ->name('leave.periods.create');
    Route::post('leave/periods', [HrLeavePeriodsController::class, 'store'])
        ->name('leave.periods.store');
    Route::get('leave/periods/{leavePeriod}/edit', [HrLeavePeriodsController::class, 'edit'])
        ->name('leave.periods.edit');
    Route::put('leave/periods/{leavePeriod}', [HrLeavePeriodsController::class, 'update'])
        ->name('leave.periods.update');

    Route::get('leave/allocations/create', [HrLeaveAllocationsController::class, 'create'])
        ->name('leave.allocations.create');
    Route::post('leave/allocations', [HrLeaveAllocationsController::class, 'store'])
        ->name('leave.allocations.store');
    Route::get('leave/allocations/{allocation}/edit', [HrLeaveAllocationsController::class, 'edit'])
        ->name('leave.allocations.edit');
    Route::put('leave/allocations/{allocation}', [HrLeaveAllocationsController::class, 'update'])
        ->name('leave.allocations.update');

    Route::get('attendance', [HrAttendanceController::class, 'index'])
        ->name('attendance.index');
    Route::post('attendance/check-in', [HrAttendanceController::class, 'checkIn'])
        ->name('attendance.check-in');
    Route::post('attendance/check-out', [HrAttendanceController::class, 'checkOut'])
        ->name('attendance.check-out');

    Route::get('attendance/requests/create', [HrAttendanceRequestsController::class, 'create'])
        ->name('attendance.requests.create');
    Route::post('attendance/requests', [HrAttendanceRequestsController::class, 'store'])
        ->name('attendance.requests.store');
    Route::get('attendance/requests/{attendanceRequest}/edit', [HrAttendanceRequestsController::class, 'edit'])
        ->name('attendance.requests.edit');
    Route::put('attendance/requests/{attendanceRequest}', [HrAttendanceRequestsController::class, 'update'])
        ->name('attendance.requests.update');
    Route::post('attendance/requests/{attendanceRequest}/submit', [HrAttendanceRequestsController::class, 'submit'])
        ->name('attendance.requests.submit');
    Route::post('attendance/requests/{attendanceRequest}/approve', [HrAttendanceRequestsController::class, 'approve'])
        ->name('attendance.requests.approve');
    Route::post('attendance/requests/{attendanceRequest}/reject', [HrAttendanceRequestsController::class, 'reject'])
        ->name('attendance.requests.reject');
    Route::post('attendance/requests/{attendanceRequest}/cancel', [HrAttendanceRequestsController::class, 'cancel'])
        ->name('attendance.requests.cancel');

    Route::get('attendance/shifts/create', [HrShiftsController::class, 'create'])
        ->name('attendance.shifts.create');
    Route::post('attendance/shifts', [HrShiftsController::class, 'store'])
        ->name('attendance.shifts.store');
    Route::get('attendance/shifts/{shift}/edit', [HrShiftsController::class, 'edit'])
        ->name('attendance.shifts.edit');
    Route::put('attendance/shifts/{shift}', [HrShiftsController::class, 'update'])
        ->name('attendance.shifts.update');

    Route::get('attendance/assignments/create', [HrShiftAssignmentsController::class, 'create'])
        ->name('attendance.assignments.create');
    Route::post('attendance/assignments', [HrShiftAssignmentsController::class, 'store'])
        ->name('attendance.assignments.store');
    Route::get('attendance/assignments/{assignment}/edit', [HrShiftAssignmentsController::class, 'edit'])
        ->name('attendance.assignments.edit');
    Route::put('attendance/assignments/{assignment}', [HrShiftAssignmentsController::class, 'update'])
        ->name('attendance.assignments.update');
});
