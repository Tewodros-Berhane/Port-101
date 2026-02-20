<?php

namespace App\Core\Platform;

use App\Core\Settings\SettingsService;

class DashboardPreferencesService
{
    public const KEY = 'platform.dashboard.preferences';

    /**
     * @var array<int, string>
     */
    public const ALLOWED_LAYOUTS = [
        'balanced',
        'analytics_first',
        'operations_first',
    ];

    /**
     * @var array<int, string>
     */
    public const ALLOWED_TABS = [
        'companies',
        'invites',
        'admin_actions',
    ];

    /**
     * @var array<int, string>
     */
    public const ALLOWED_WIDGETS = [
        'delivery_performance',
        'governance_snapshot',
        'operations_presets',
        'operations_detail',
    ];

    public function __construct(
        private SettingsService $settingsService
    ) {}

    /**
     * @return array{
     *  default_preset_id: string|null,
     *  default_operations_tab: string,
     *  layout: string,
     *  hidden_widgets: array<int, string>
     * }
     */
    public function get(?string $userId): array
    {
        if (! $userId) {
            return $this->defaults();
        }

        $stored = $this->settingsService->get(self::KEY, null, null, $userId);

        if (! is_array($stored)) {
            return $this->defaults();
        }

        return $this->normalize($stored);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *  default_preset_id: string|null,
     *  default_operations_tab: string,
     *  layout: string,
     *  hidden_widgets: array<int, string>
     * }
     */
    public function set(array $data, ?string $userId, ?string $actorId = null): array
    {
        if (! $userId) {
            return $this->defaults();
        }

        $current = $this->get($userId);
        $normalized = $this->normalize([
            ...$current,
            ...$data,
        ]);

        $this->settingsService->set(
            self::KEY,
            $normalized,
            null,
            $userId,
            $actorId
        );

        return $normalized;
    }

    /**
     * @return array{
     *  default_preset_id: string|null,
     *  default_operations_tab: string,
     *  layout: string,
     *  hidden_widgets: array<int, string>
     * }
     */
    private function defaults(): array
    {
        return [
            'default_preset_id' => null,
            'default_operations_tab' => 'companies',
            'layout' => 'balanced',
            'hidden_widgets' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *  default_preset_id: string|null,
     *  default_operations_tab: string,
     *  layout: string,
     *  hidden_widgets: array<int, string>
     * }
     */
    private function normalize(array $input): array
    {
        $defaults = $this->defaults();

        $defaultPresetId = $input['default_preset_id'] ?? null;
        if (! is_string($defaultPresetId) || trim($defaultPresetId) === '') {
            $defaultPresetId = null;
        } else {
            $defaultPresetId = trim($defaultPresetId);
        }

        $defaultOperationsTab = (string) ($input['default_operations_tab'] ?? $defaults['default_operations_tab']);
        if (! in_array($defaultOperationsTab, self::ALLOWED_TABS, true)) {
            $defaultOperationsTab = $defaults['default_operations_tab'];
        }

        $layout = (string) ($input['layout'] ?? $defaults['layout']);
        if (! in_array($layout, self::ALLOWED_LAYOUTS, true)) {
            $layout = $defaults['layout'];
        }

        $hiddenWidgetsRaw = $input['hidden_widgets'] ?? [];
        $hiddenWidgets = is_array($hiddenWidgetsRaw)
            ? collect($hiddenWidgetsRaw)
                ->filter(fn ($value) => is_string($value))
                ->map(fn (string $value) => trim($value))
                ->filter(fn (string $value) => in_array($value, self::ALLOWED_WIDGETS, true))
                ->unique()
                ->values()
                ->all()
            : [];

        return [
            'default_preset_id' => $defaultPresetId,
            'default_operations_tab' => $defaultOperationsTab,
            'layout' => $layout,
            'hidden_widgets' => $hiddenWidgets,
        ];
    }
}
