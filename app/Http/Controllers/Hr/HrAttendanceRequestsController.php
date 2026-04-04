<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrAttendanceDecisionRequest;
use App\Http\Requests\Hr\HrAttendanceRequestStoreRequest;
use App\Http\Requests\Hr\HrAttendanceRequestUpdateRequest;
use App\Models\User;
use App\Support\Http\Feedback;
use App\Modules\Approvals\ApprovalQueueService;
use App\Modules\Hr\HrAttendanceService;
use App\Modules\Hr\HrAttendanceWorkspaceService;
use App\Modules\Hr\Models\HrAttendanceRecord;
use App\Modules\Hr\Models\HrAttendanceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HrAttendanceRequestsController extends Controller
{
    public function create(Request $request, HrAttendanceWorkspaceService $workspaceService): Response
    {
        $this->authorize('create', HrAttendanceRequest::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return Inertia::render('hr/attendance/requests/create', [
            'employeeOptions' => $workspaceService->employeeOptions($user),
            'form' => [
                'employee_id' => $workspaceService->linkedEmployeeId($user) ?? '',
                'from_date' => now()->toDateString(),
                'to_date' => now()->toDateString(),
                'requested_status' => HrAttendanceRecord::STATUS_PRESENT,
                'requested_check_in_at' => '09:00',
                'requested_check_out_at' => '17:00',
                'reason' => '',
                'action' => 'submit',
            ],
            'statuses' => HrAttendanceRecord::STATUSES,
        ]);
    }

    public function store(
        HrAttendanceRequestStoreRequest $request,
        HrAttendanceService $attendanceService,
        ApprovalQueueService $approvalQueueService,
    ): RedirectResponse {
        $this->authorize('create', HrAttendanceRequest::class);

        try {
            $attendanceRequest = $attendanceService->createRequest($request->validated(), $request->user());

            if (($request->validated('action') ?? 'save') === 'submit') {
                $attendanceService->submit($attendanceRequest, $request->user()->id);
            }

            $approvalQueueService->syncPendingRequests($request->user()->currentCompany, $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('company.hr.attendance.index')
            ->with('success', sprintf('Attendance correction %s saved.', $attendanceRequest->request_number));
    }

    public function edit(Request $request, HrAttendanceRequest $attendanceRequest, HrAttendanceWorkspaceService $workspaceService): Response
    {
        $this->authorize('update', $attendanceRequest);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return Inertia::render('hr/attendance/requests/edit', [
            'employeeOptions' => $workspaceService->employeeOptions($user),
            'requestRecord' => [
                'id' => $attendanceRequest->id,
                'employee_id' => $attendanceRequest->employee_id,
                'from_date' => $attendanceRequest->from_date?->toDateString(),
                'to_date' => $attendanceRequest->to_date?->toDateString(),
                'requested_status' => $attendanceRequest->requested_status,
                'requested_check_in_at' => $attendanceRequest->requested_check_in_at?->format('H:i') ?? '',
                'requested_check_out_at' => $attendanceRequest->requested_check_out_at?->format('H:i') ?? '',
                'reason' => $attendanceRequest->reason,
                'action' => 'save',
            ],
            'statuses' => HrAttendanceRecord::STATUSES,
        ]);
    }

    public function update(
        HrAttendanceRequestUpdateRequest $request,
        HrAttendanceRequest $attendanceRequest,
        HrAttendanceService $attendanceService,
        ApprovalQueueService $approvalQueueService,
    ): RedirectResponse {
        $this->authorize('update', $attendanceRequest);

        try {
            $attendanceRequest = $attendanceService->updateRequest($attendanceRequest, $request->validated(), $request->user());

            if (($request->validated('action') ?? 'save') === 'submit') {
                $attendanceService->submit($attendanceRequest, $request->user()->id);
            }

            $approvalQueueService->syncPendingRequests($request->user()->currentCompany, $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('company.hr.attendance.index')
            ->with('success', 'Attendance correction updated.');
    }

    public function submit(Request $request, HrAttendanceRequest $attendanceRequest, HrAttendanceService $attendanceService, ApprovalQueueService $approvalQueueService): RedirectResponse
    {
        $this->authorize('submit', $attendanceRequest);

        try {
            $attendanceService->submit($attendanceRequest, $request->user()?->id);
            $approvalQueueService->syncPendingRequests($request->user()?->currentCompany, $request->user()?->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', Feedback::flash($request, 'Attendance correction submitted.'));
    }

    public function approve(Request $request, HrAttendanceRequest $attendanceRequest, HrAttendanceService $attendanceService, ApprovalQueueService $approvalQueueService): RedirectResponse
    {
        $this->authorize('approve', $attendanceRequest);

        try {
            $attendanceService->approve($attendanceRequest, $request->user()?->id);
            $approvalQueueService->syncPendingRequests($request->user()?->currentCompany, $request->user()?->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', Feedback::flash($request, 'Attendance correction approved.'));
    }

    public function reject(HrAttendanceDecisionRequest $request, HrAttendanceRequest $attendanceRequest, HrAttendanceService $attendanceService, ApprovalQueueService $approvalQueueService): RedirectResponse
    {
        $this->authorize('reject', $attendanceRequest);

        try {
            $attendanceService->reject($attendanceRequest, $request->validated('reason'), $request->user()?->id);
            $approvalQueueService->syncPendingRequests($request->user()?->currentCompany, $request->user()?->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', Feedback::flash($request, 'Attendance correction rejected.'));
    }

    public function cancel(Request $request, HrAttendanceRequest $attendanceRequest, HrAttendanceService $attendanceService, ApprovalQueueService $approvalQueueService): RedirectResponse
    {
        $this->authorize('cancel', $attendanceRequest);

        try {
            $attendanceService->cancel($attendanceRequest, $request->user()?->id);
            $approvalQueueService->syncPendingRequests($request->user()?->currentCompany, $request->user()?->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', Feedback::flash($request, 'Attendance correction cancelled.'));
    }
}
