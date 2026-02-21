<?php

namespace App\Http\Controllers\Company;

use App\Core\Notifications\NotificationGovernanceService;
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
    /**
     * @var array<int, string>
     */
    private const COMPANY_SETTING_KEYS = [
        'company.fiscal_year_start',
        'company.locale',
        'company.date_format',
        'company.number_format',
        'core.audit_logs.retention_days',
        'company.tax_period',
        'company.tax_submission_day',
        'company.approvals.enabled',
        'company.approvals.policy',
        'company.approvals.threshold_amount',
        'company.approvals.escalation_hours',
        'company.numbering.sales_order_prefix',
        'company.numbering.sales_order_next',
        'company.numbering.purchase_order_prefix',
        'company.numbering.purchase_order_next',
        'company.numbering.invoice_prefix',
        'company.numbering.invoice_next',
    ];

    public function show(Request $request, SettingsService $settingsService): Response
    {
        abort_unless($request->user()?->hasPermission('core.company.view'), 403);

        $company = $request->user()?->currentCompany;
        $settings = $company
            ? $settingsService->getMany(self::COMPANY_SETTING_KEYS, $company->id)
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
                'tax_period' => $settings['company.tax_period'] ?? 'monthly',
                'tax_submission_day' => $settings['company.tax_submission_day'] ?? 15,
                'approval_enabled' => (bool) ($settings['company.approvals.enabled'] ?? false),
                'approval_policy' => $settings['company.approvals.policy'] ?? 'none',
                'approval_threshold_amount' => $settings['company.approvals.threshold_amount']
                    ?? 10000,
                'approval_escalation_hours' => $settings['company.approvals.escalation_hours'] ?? 24,
                'sales_order_prefix' => $settings['company.numbering.sales_order_prefix'] ?? 'SO',
                'sales_order_next_number' => $settings['company.numbering.sales_order_next'] ?? 1001,
                'purchase_order_prefix' => $settings['company.numbering.purchase_order_prefix'] ?? 'PO',
                'purchase_order_next_number' => $settings['company.numbering.purchase_order_next'] ?? 1001,
                'invoice_prefix' => $settings['company.numbering.invoice_prefix'] ?? 'INV',
                'invoice_next_number' => $settings['company.numbering.invoice_next'] ?? 1001,
            ],
        ]);
    }

    public function update(
        CompanySettingsUpdateRequest $request,
        SettingsService $settingsService,
        NotificationGovernanceService $notificationGovernance
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
        $settingsService->set(
            'company.tax_period',
            $data['tax_period'] ?? 'monthly',
            $companyId,
            null,
            $actorId
        );
        $settingsService->set(
            'company.tax_submission_day',
            $data['tax_submission_day'] ?? 15,
            $companyId,
            null,
            $actorId
        );
        $settingsService->set(
            'company.approvals.enabled',
            (bool) ($data['approval_enabled'] ?? false),
            $companyId,
            null,
            $actorId
        );
        $settingsService->set(
            'company.approvals.policy',
            $data['approval_policy'] ?? 'none',
            $companyId,
            null,
            $actorId
        );
        $settingsService->set(
            'company.approvals.threshold_amount',
            $data['approval_threshold_amount'] ?? 10000,
            $companyId,
            null,
            $actorId
        );
        $settingsService->set(
            'company.approvals.escalation_hours',
            $data['approval_escalation_hours'] ?? 24,
            $companyId,
            null,
            $actorId
        );
        $settingsService->set(
            'company.numbering.sales_order_prefix',
            $data['sales_order_prefix'] ?? 'SO',
            $companyId,
            null,
            $actorId
        );
        $settingsService->set(
            'company.numbering.sales_order_next',
            $data['sales_order_next_number'] ?? 1001,
            $companyId,
            null,
            $actorId
        );
        $settingsService->set(
            'company.numbering.purchase_order_prefix',
            $data['purchase_order_prefix'] ?? 'PO',
            $companyId,
            null,
            $actorId
        );
        $settingsService->set(
            'company.numbering.purchase_order_next',
            $data['purchase_order_next_number'] ?? 1001,
            $companyId,
            null,
            $actorId
        );
        $settingsService->set(
            'company.numbering.invoice_prefix',
            $data['invoice_prefix'] ?? 'INV',
            $companyId,
            null,
            $actorId
        );
        $settingsService->set(
            'company.numbering.invoice_next',
            $data['invoice_next_number'] ?? 1001,
            $companyId,
            null,
            $actorId
        );

        $recipients = $company->users()
            ->where('users.id', '!=', $actorId)
            ->get();

        $notificationGovernance->notify(
            recipients: $recipients,
            notification: new CompanySettingsUpdatedNotification(
                companyName: $company->name,
                updatedBy: $request->user()?->name ?? 'System'
            ),
            severity: 'medium',
            context: [
                'event' => 'Company settings updated',
                'source' => 'company.settings',
            ]
        );

        return redirect()
            ->route('company.settings.show')
            ->with('success', 'Company settings updated.');
    }
}
