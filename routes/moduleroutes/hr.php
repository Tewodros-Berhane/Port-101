<?php

use App\Http\Controllers\Hr\HrAttendanceController;
use App\Http\Controllers\Hr\HrAttendanceRequestsController;
use App\Http\Controllers\Hr\HrCompensationAssignmentsController;
use App\Http\Controllers\Hr\HrDashboardController;
use App\Http\Controllers\Hr\HrEmployeeContractsController;
use App\Http\Controllers\Hr\HrEmployeeDocumentsController;
use App\Http\Controllers\Hr\HrEmployeesController;
use App\Http\Controllers\Hr\HrLeaveAllocationsController;
use App\Http\Controllers\Hr\HrLeavePeriodsController;
use App\Http\Controllers\Hr\HrLeaveRequestsController;
use App\Http\Controllers\Hr\HrLeaveTypesController;
use App\Http\Controllers\Hr\HrPayrollController;
use App\Http\Controllers\Hr\HrPayrollPeriodsController;
use App\Http\Controllers\Hr\HrPayrollRunsController;
use App\Http\Controllers\Hr\HrPayslipsController;
use App\Http\Controllers\Hr\HrReimbursementCategoriesController;
use App\Http\Controllers\Hr\HrReimbursementClaimsController;
use App\Http\Controllers\Hr\HrReimbursementReceiptsController;
use App\Http\Controllers\Hr\HrReimbursementsController;
use App\Http\Controllers\Hr\HrReportsController;
use App\Http\Controllers\Hr\HrSalaryStructuresController;
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
    Route::post('employees/{employee}/access/resend', [HrEmployeesController::class, 'resendInvite'])
        ->name('employees.access.resend');
    Route::delete('employees/{employee}/access/invite', [HrEmployeesController::class, 'cancelInvite'])
        ->name('employees.access.cancel');
    Route::patch('employees/{employee}/access/deactivate', [HrEmployeesController::class, 'deactivateAccess'])
        ->name('employees.access.deactivate');
    Route::post('employees/{employee}/access/reactivate', [HrEmployeesController::class, 'reactivateAccess'])
        ->name('employees.access.reactivate');
    Route::patch('employees/{employee}/access/role', [HrEmployeesController::class, 'updateAccessRole'])
        ->name('employees.access.role');

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

    Route::get('reimbursements', [HrReimbursementsController::class, 'index'])
        ->name('reimbursements.index');

    Route::get('reimbursements/categories/create', [HrReimbursementCategoriesController::class, 'create'])
        ->name('reimbursements.categories.create');
    Route::post('reimbursements/categories', [HrReimbursementCategoriesController::class, 'store'])
        ->name('reimbursements.categories.store');
    Route::get('reimbursements/categories/{category}/edit', [HrReimbursementCategoriesController::class, 'edit'])
        ->name('reimbursements.categories.edit');
    Route::put('reimbursements/categories/{category}', [HrReimbursementCategoriesController::class, 'update'])
        ->name('reimbursements.categories.update');

    Route::get('reimbursements/claims/create', [HrReimbursementClaimsController::class, 'create'])
        ->name('reimbursements.claims.create');
    Route::post('reimbursements/claims', [HrReimbursementClaimsController::class, 'store'])
        ->name('reimbursements.claims.store');
    Route::get('reimbursements/claims/{claim}/edit', [HrReimbursementClaimsController::class, 'edit'])
        ->name('reimbursements.claims.edit');
    Route::put('reimbursements/claims/{claim}', [HrReimbursementClaimsController::class, 'update'])
        ->name('reimbursements.claims.update');
    Route::post('reimbursements/claims/{claim}/submit', [HrReimbursementClaimsController::class, 'submit'])
        ->name('reimbursements.claims.submit');
    Route::post('reimbursements/claims/{claim}/approve', [HrReimbursementClaimsController::class, 'approve'])
        ->name('reimbursements.claims.approve');
    Route::post('reimbursements/claims/{claim}/reject', [HrReimbursementClaimsController::class, 'reject'])
        ->name('reimbursements.claims.reject');
    Route::post('reimbursements/claims/{claim}/post', [HrReimbursementClaimsController::class, 'postToAccounting'])
        ->name('reimbursements.claims.post');
    Route::post('reimbursements/claims/{claim}/pay', [HrReimbursementClaimsController::class, 'recordPayment'])
        ->name('reimbursements.claims.pay');

    Route::post('reimbursements/lines/{line}/receipt', [HrReimbursementReceiptsController::class, 'store'])
        ->name('reimbursements.receipts.store');
    Route::get('reimbursements/lines/{line}/receipt/download', [HrReimbursementReceiptsController::class, 'download'])
        ->name('reimbursements.receipts.download');
    Route::delete('reimbursements/lines/{line}/receipt', [HrReimbursementReceiptsController::class, 'destroy'])
        ->name('reimbursements.receipts.destroy');

    Route::get('payroll', [HrPayrollController::class, 'index'])
        ->name('payroll.index');

    Route::get('payroll/structures/create', [HrSalaryStructuresController::class, 'create'])
        ->name('payroll.structures.create');
    Route::post('payroll/structures', [HrSalaryStructuresController::class, 'store'])
        ->name('payroll.structures.store');
    Route::get('payroll/structures/{structure}/edit', [HrSalaryStructuresController::class, 'edit'])
        ->name('payroll.structures.edit');
    Route::put('payroll/structures/{structure}', [HrSalaryStructuresController::class, 'update'])
        ->name('payroll.structures.update');

    Route::get('payroll/assignments/create', [HrCompensationAssignmentsController::class, 'create'])
        ->name('payroll.assignments.create');
    Route::post('payroll/assignments', [HrCompensationAssignmentsController::class, 'store'])
        ->name('payroll.assignments.store');
    Route::get('payroll/assignments/{assignment}/edit', [HrCompensationAssignmentsController::class, 'edit'])
        ->name('payroll.assignments.edit');
    Route::put('payroll/assignments/{assignment}', [HrCompensationAssignmentsController::class, 'update'])
        ->name('payroll.assignments.update');

    Route::get('payroll/periods/create', [HrPayrollPeriodsController::class, 'create'])
        ->name('payroll.periods.create');
    Route::post('payroll/periods', [HrPayrollPeriodsController::class, 'store'])
        ->name('payroll.periods.store');
    Route::get('payroll/periods/{period}/edit', [HrPayrollPeriodsController::class, 'edit'])
        ->name('payroll.periods.edit');
    Route::put('payroll/periods/{period}', [HrPayrollPeriodsController::class, 'update'])
        ->name('payroll.periods.update');

    Route::get('payroll/runs/create', [HrPayrollRunsController::class, 'create'])
        ->name('payroll.runs.create');
    Route::post('payroll/runs', [HrPayrollRunsController::class, 'store'])
        ->name('payroll.runs.store');
    Route::get('payroll/runs/{run}', [HrPayrollRunsController::class, 'show'])
        ->name('payroll.runs.show');
    Route::post('payroll/runs/{run}/prepare', [HrPayrollRunsController::class, 'prepare'])
        ->name('payroll.runs.prepare');
    Route::post('payroll/runs/{run}/approve', [HrPayrollRunsController::class, 'approve'])
        ->name('payroll.runs.approve');
    Route::post('payroll/runs/{run}/reject', [HrPayrollRunsController::class, 'reject'])
        ->name('payroll.runs.reject');
    Route::post('payroll/runs/{run}/post', [HrPayrollRunsController::class, 'post'])
        ->name('payroll.runs.post');

    Route::get('payroll/payslips/{payslip}', [HrPayslipsController::class, 'show'])
        ->name('payroll.payslips.show');

    Route::get('reports', [HrReportsController::class, 'index'])
        ->name('reports.index');
    Route::get('reports/export/{reportKey}', [HrReportsController::class, 'export'])
        ->name('reports.export');
});
