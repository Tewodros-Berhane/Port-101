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
        ];
    }
}
