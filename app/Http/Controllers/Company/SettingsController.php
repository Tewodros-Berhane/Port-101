<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CompanySettingsUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function show(Request $request): Response
    {
        abort_unless($request->user()?->hasPermission('core.company.view'), 403);

        $company = $request->user()?->currentCompany;

        return Inertia::render('company/settings', [
            'company' => [
                'id' => $company?->id,
                'name' => $company?->name,
                'slug' => $company?->slug,
                'timezone' => $company?->timezone,
                'currency_code' => $company?->currency_code,
                'owner' => $company?->owner?->name,
                'owner_email' => $company?->owner?->email,
            ],
        ]);
    }

    public function update(CompanySettingsUpdateRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('core.settings.manage'), 403);

        $company = $request->user()?->currentCompany;

        if (! $company) {
            abort(404);
        }

        $data = $request->validated();

        $company->update([
            'name' => $data['name'],
            'timezone' => $data['timezone'],
            'currency_code' => $data['currency_code']
                ? strtoupper($data['currency_code'])
                : null,
        ]);

        return redirect()
            ->route('company.settings.show')
            ->with('success', 'Company settings updated.');
    }
}
