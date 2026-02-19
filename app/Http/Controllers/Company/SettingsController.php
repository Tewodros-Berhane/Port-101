<?php

namespace App\Http\Controllers\Company;

use App\Core\Settings\SettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CompanySettingsUpdateRequest;
use App\Notifications\CompanySettingsUpdatedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function show(Request $request, SettingsService $settingsService): Response
    {
        abort_unless($request->user()?->hasPermission('core.company.view'), 403);

        $company = $request->user()?->currentCompany;
        $settings = $company
            ? $settingsService->getMany([
                'company.fiscal_year_start',
                'company.locale',
                'company.date_format',
                'company.number_format',
                'core.audit_logs.retention_days',
            ], $company->id)
            : [];

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
            'settings' => [
                'fiscal_year_start' => $settings['company.fiscal_year_start'] ?? null,
                'locale' => $settings['company.locale'] ?? null,
                'date_format' => $settings['company.date_format'] ?? 'Y-m-d',
                'number_format' => $settings['company.number_format'] ?? '1,234.56',
                'audit_retention_days' => $settings['core.audit_logs.retention_days']
                    ?? (int) config('core.audit_logs.retention_days', 365),
            ],
        ]);
    }

    public function update(
        CompanySettingsUpdateRequest $request,
        SettingsService $settingsService
    ): RedirectResponse {
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

        $companyId = $company->id;
        $actorId = $request->user()?->id;

        $settingsService->set(
            'company.fiscal_year_start',
            $data['fiscal_year_start'] ?? null,
            $companyId,
            null,
            $actorId
        );
        $settingsService->set(
            'company.locale',
            $data['locale'] ?? null,
            $companyId,
            null,
            $actorId
        );
        $settingsService->set(
            'company.date_format',
            $data['date_format'] ?? 'Y-m-d',
            $companyId,
            null,
            $actorId
        );
        $settingsService->set(
            'company.number_format',
            $data['number_format'] ?? '1,234.56',
            $companyId,
            null,
            $actorId
        );
        $settingsService->set(
            'core.audit_logs.retention_days',
            $data['audit_retention_days'] ?? (int) config('core.audit_logs.retention_days', 365),
            $companyId,
            null,
            $actorId
        );

        $company->users()
            ->where('users.id', '!=', $actorId)
            ->get()
            ->each(function ($user) use ($company, $request) {
                $user->notify(new CompanySettingsUpdatedNotification(
                    companyName: $company->name,
                    updatedBy: $request->user()?->name ?? 'System'
                ));
            });

        return redirect()
            ->route('company.settings.show')
            ->with('success', 'Company settings updated.');
    }
}
