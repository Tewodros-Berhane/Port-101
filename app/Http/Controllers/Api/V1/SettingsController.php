<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Settings\SettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function index(Request $request, SettingsService $settingsService): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('core.company.view'), 403);

        $companyId = $request->user()?->current_company_id;

        $settings = $settingsService->getMany(self::COMPANY_SETTING_KEYS, $companyId);

        return response()->json([
            'data' => $this->mapSettingsPayload($settings),
        ]);
    }

    public function update(Request $request, SettingsService $settingsService): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('core.settings.manage'), 403);

        $data = $request->validate([
            'fiscal_year_start' => ['nullable', 'date_format:Y-m-d'],
            'locale' => ['nullable', 'string', 'max:10'],
            'date_format' => ['nullable', 'string', Rule::in(['Y-m-d', 'd/m/Y', 'm/d/Y'])],
            'number_format' => ['nullable', 'string', Rule::in(['1,234.56', '1.234,56', '1 234,56'])],
            'audit_retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'tax_period' => ['nullable', 'string', Rule::in(['monthly', 'quarterly', 'annual'])],
            'tax_submission_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'approval_enabled' => ['nullable', 'boolean'],
            'approval_policy' => ['nullable', 'string', Rule::in(['none', 'amount_based', 'always'])],
            'approval_threshold_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'approval_escalation_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'sales_order_prefix' => ['nullable', 'string', 'max:12', 'regex:/^[A-Z0-9-]+$/'],
            'sales_order_next_number' => ['nullable', 'integer', 'min:1', 'max:999999999'],
            'purchase_order_prefix' => ['nullable', 'string', 'max:12', 'regex:/^[A-Z0-9-]+$/'],
            'purchase_order_next_number' => ['nullable', 'integer', 'min:1', 'max:999999999'],
            'invoice_prefix' => ['nullable', 'string', 'max:12', 'regex:/^[A-Z0-9-]+$/'],
            'invoice_next_number' => ['nullable', 'integer', 'min:1', 'max:999999999'],
        ]);

        $companyId = $request->user()?->current_company_id;
        $actorId = $request->user()?->id;

        if (array_key_exists('fiscal_year_start', $data)) {
            $settingsService->set(
                'company.fiscal_year_start',
                $data['fiscal_year_start'],
                $companyId,
                null,
                $actorId
            );
        }
        if (array_key_exists('locale', $data)) {
            $settingsService->set(
                'company.locale',
                $data['locale'],
                $companyId,
                null,
                $actorId
            );
        }
        if (array_key_exists('date_format', $data)) {
            $settingsService->set(
                'company.date_format',
                $data['date_format'],
                $companyId,
                null,
                $actorId
            );
        }
        if (array_key_exists('number_format', $data)) {
            $settingsService->set(
                'company.number_format',
                $data['number_format'],
                $companyId,
                null,
                $actorId
            );
        }
        if (array_key_exists('audit_retention_days', $data)) {
            $settingsService->set(
                'core.audit_logs.retention_days',
                $data['audit_retention_days'],
                $companyId,
                null,
                $actorId
            );
        }
        if (array_key_exists('tax_period', $data)) {
            $settingsService->set(
                'company.tax_period',
                $data['tax_period'],
                $companyId,
                null,
                $actorId
            );
        }
        if (array_key_exists('tax_submission_day', $data)) {
            $settingsService->set(
                'company.tax_submission_day',
                $data['tax_submission_day'],
                $companyId,
                null,
                $actorId
            );
        }
        if (array_key_exists('approval_enabled', $data)) {
            $settingsService->set(
                'company.approvals.enabled',
                (bool) $data['approval_enabled'],
                $companyId,
                null,
                $actorId
            );
        }
        if (array_key_exists('approval_policy', $data)) {
            $settingsService->set(
                'company.approvals.policy',
                $data['approval_policy'],
                $companyId,
                null,
                $actorId
            );
        }
        if (array_key_exists('approval_threshold_amount', $data)) {
            $settingsService->set(
                'company.approvals.threshold_amount',
                $data['approval_threshold_amount'],
                $companyId,
                null,
                $actorId
            );
        }
        if (array_key_exists('approval_escalation_hours', $data)) {
            $settingsService->set(
                'company.approvals.escalation_hours',
                $data['approval_escalation_hours'],
                $companyId,
                null,
                $actorId
            );
        }
        if (array_key_exists('sales_order_prefix', $data)) {
            $settingsService->set(
                'company.numbering.sales_order_prefix',
                $data['sales_order_prefix'],
                $companyId,
                null,
                $actorId
            );
        }
        if (array_key_exists('sales_order_next_number', $data)) {
            $settingsService->set(
                'company.numbering.sales_order_next',
                $data['sales_order_next_number'],
                $companyId,
                null,
                $actorId
            );
        }
        if (array_key_exists('purchase_order_prefix', $data)) {
            $settingsService->set(
                'company.numbering.purchase_order_prefix',
                $data['purchase_order_prefix'],
                $companyId,
                null,
                $actorId
            );
        }
        if (array_key_exists('purchase_order_next_number', $data)) {
            $settingsService->set(
                'company.numbering.purchase_order_next',
                $data['purchase_order_next_number'],
                $companyId,
                null,
                $actorId
            );
        }
        if (array_key_exists('invoice_prefix', $data)) {
            $settingsService->set(
                'company.numbering.invoice_prefix',
                $data['invoice_prefix'],
                $companyId,
                null,
                $actorId
            );
        }
        if (array_key_exists('invoice_next_number', $data)) {
            $settingsService->set(
                'company.numbering.invoice_next',
                $data['invoice_next_number'],
                $companyId,
                null,
                $actorId
            );
        }

        $settings = $settingsService->getMany(self::COMPANY_SETTING_KEYS, $companyId);

        return response()->json([
            'data' => $this->mapSettingsPayload($settings),
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function mapSettingsPayload(array $settings): array
    {
        return [
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
            'approval_threshold_amount' => $settings['company.approvals.threshold_amount'] ?? 10000,
            'approval_escalation_hours' => $settings['company.approvals.escalation_hours'] ?? 24,
            'sales_order_prefix' => $settings['company.numbering.sales_order_prefix'] ?? 'SO',
            'sales_order_next_number' => $settings['company.numbering.sales_order_next'] ?? 1001,
            'purchase_order_prefix' => $settings['company.numbering.purchase_order_prefix'] ?? 'PO',
            'purchase_order_next_number' => $settings['company.numbering.purchase_order_next'] ?? 1001,
            'invoice_prefix' => $settings['company.numbering.invoice_prefix'] ?? 'INV',
            'invoice_next_number' => $settings['company.numbering.invoice_next'] ?? 1001,
        ];
    }
}
