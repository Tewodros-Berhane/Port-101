<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrLeavePeriodStoreRequest;
use App\Http\Requests\Hr\HrLeavePeriodUpdateRequest;
use App\Modules\Hr\HrLeaveService;
use App\Modules\Hr\Models\HrLeavePeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HrLeavePeriodsController extends Controller
{
    public function create(): Response
    {
        $this->authorize('create', HrLeavePeriod::class);

        return Inertia::render('hr/leave/periods/create', [
            'form' => [
                'name' => '',
                'start_date' => now()->startOfYear()->toDateString(),
                'end_date' => now()->endOfYear()->toDateString(),
                'is_closed' => false,
            ],
        ]);
    }

    public function store(HrLeavePeriodStoreRequest $request, HrLeaveService $leaveService): RedirectResponse
    {
        $this->authorize('create', HrLeavePeriod::class);

        try {
            $leaveService->createPeriod($request->validated(), $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('company.hr.leave.index')->with('success', 'Leave period created.');
    }

    public function edit(HrLeavePeriod $leavePeriod): Response
    {
        $this->authorize('update', $leavePeriod);

        return Inertia::render('hr/leave/periods/edit', [
            'leavePeriod' => [
                'id' => $leavePeriod->id,
                'name' => $leavePeriod->name,
                'start_date' => $leavePeriod->start_date?->toDateString(),
                'end_date' => $leavePeriod->end_date?->toDateString(),
                'is_closed' => (bool) $leavePeriod->is_closed,
            ],
        ]);
    }

    public function update(HrLeavePeriodUpdateRequest $request, HrLeavePeriod $leavePeriod, HrLeaveService $leaveService): RedirectResponse
    {
        $this->authorize('update', $leavePeriod);

        try {
            $leaveService->updatePeriod($leavePeriod, $request->validated(), $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('company.hr.leave.index')->with('success', 'Leave period updated.');
    }
}
