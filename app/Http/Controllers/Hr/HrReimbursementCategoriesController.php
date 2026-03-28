<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrReimbursementCategoryStoreRequest;
use App\Http\Requests\Hr\HrReimbursementCategoryUpdateRequest;
use App\Modules\Hr\HrReimbursementService;
use App\Modules\Hr\Models\HrReimbursementCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HrReimbursementCategoriesController extends Controller
{
    public function create(): Response
    {
        $this->authorize('create', HrReimbursementCategory::class);

        return Inertia::render('hr/reimbursements/categories/create', [
            'form' => [
                'name' => '',
                'code' => '',
                'default_expense_account_reference' => '',
                'requires_receipt' => false,
                'is_project_rebillable' => false,
            ],
        ]);
    }

    public function store(HrReimbursementCategoryStoreRequest $request, HrReimbursementService $service): RedirectResponse
    {
        $this->authorize('create', HrReimbursementCategory::class);

        try {
            $service->createCategory($request->validated(), $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('company.hr.reimbursements.index')
            ->with('success', 'Reimbursement category created.');
    }

    public function edit(HrReimbursementCategory $category): Response
    {
        $this->authorize('update', $category);

        return Inertia::render('hr/reimbursements/categories/edit', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code ?? '',
                'default_expense_account_reference' => $category->default_expense_account_reference ?? '',
                'requires_receipt' => (bool) $category->requires_receipt,
                'is_project_rebillable' => (bool) $category->is_project_rebillable,
            ],
        ]);
    }

    public function update(
        HrReimbursementCategoryUpdateRequest $request,
        HrReimbursementCategory $category,
        HrReimbursementService $service,
    ): RedirectResponse {
        $this->authorize('update', $category);

        try {
            $service->updateCategory($category, $request->validated(), $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('company.hr.reimbursements.index')
            ->with('success', 'Reimbursement category updated.');
    }
}
