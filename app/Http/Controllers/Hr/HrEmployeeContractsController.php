<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrEmployeeContractStoreRequest;
use App\Http\Requests\Hr\HrEmployeeContractUpdateRequest;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrEmployeeContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class HrEmployeeContractsController extends Controller
{
    public function store(HrEmployeeContractStoreRequest $request, HrEmployee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);
        abort_unless($request->user()?->can('create', HrEmployeeContract::class), 403);

        HrEmployeeContract::create([
            ...$request->validated(),
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'contract_number' => $this->resolveContractNumber($employee, $request->input('contract_number')),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return back(303)->with('success', 'Employee contract added.');
    }

    public function update(HrEmployeeContractUpdateRequest $request, HrEmployeeContract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);

        $contract->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return back(303)->with('success', 'Employee contract updated.');
    }

    public function destroy(HrEmployeeContract $contract): RedirectResponse
    {
        $this->authorize('delete', $contract);

        $contract->delete();

        return back(303)->with('success', 'Employee contract removed.');
    }

    private function resolveContractNumber(HrEmployee $employee, ?string $proposed): string
    {
        $candidate = trim((string) $proposed);

        if ($candidate !== '') {
            return $candidate;
        }

        $prefix = 'CON-'.$employee->employee_number.'-';
        $count = HrEmployeeContract::query()
            ->withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->count() + 1;

        return $prefix.Str::padLeft((string) $count, 2, '0');
    }
}
