<?php

namespace App\Http\Controllers\Platform;

use App\Core\Access\Models\Invite;
use App\Core\Audit\Models\AuditLog;
use App\Core\Company\Models\Company;
use App\Core\Notifications\NotificationGovernanceService;
use App\Core\Platform\DashboardPreferencesService;
use App\Core\Platform\OperationsReportingSettingsService;
use App\Core\Platform\PlatformReportExportService;
use App\Core\Platform\PlatformReportsService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        NotificationGovernanceService $notificationGovernance,
        OperationsReportingSettingsService $operationsSettings,
        DashboardPreferencesService $dashboardPreferences
    ): Response {
        $userId = $request->user()?->id;
        $preferences = $dashboardPreferences->get($userId);
        $validatedFilters = $this->validatedOperationsFilters($request);

        if (! $this->hasAnyOperationsFilterInput($request)) {
            $validatedFilters = [
                ...$validatedFilters,
                ...$operationsSettings->filtersForPreset(
                    $preferences['default_preset_id'] ?? null
                ),
            ];
        }

        $normalizedFilters = $operationsSettings->normalizeFilters($validatedFilters);
        $trendWindow = (int) ($normalizedFilters['trend_window'] ?? 30);
        $adminFilters = $this->normalizedAdminFilters($normalizedFilters);
        $operationsTab = $this->normalizedOperationsTab(
            $request->input('operations_tab', $preferences['default_operations_tab'] ?? 'companies')
        );

        $stats = [
            'companies' => Company::query()->count(),
            'active_companies' => Company::query()->where('is_active', true)->count(),
            'users' => User::query()->count(),
            'audit_logs' => AuditLog::query()->count(),
        ];

        $recentCompanies = Company::query()
            ->with('owner:id,name,email')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (Company $company) {
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'owner' => $company->owner?->name,
                    'is_active' => $company->is_active,
                    'created_at' => $company->created_at?->toIso8601String(),
                ];
            });

        $recentInvitesQuery = Invite::query()
            ->with(['company:id,name', 'creator:id,name'])
            ->orderByDesc('created_at');

        $inviteDeliveryStatus = $adminFilters['invite_delivery_status'] ?? null;
        if ($inviteDeliveryStatus === Invite::DELIVERY_PENDING) {
            $recentInvitesQuery->where(function (Builder $query): void {
                $query->whereNull('delivery_status')
                    ->orWhere('delivery_status', Invite::DELIVERY_PENDING);
            });
        } elseif (
            in_array($inviteDeliveryStatus, [Invite::DELIVERY_SENT, Invite::DELIVERY_FAILED], true)
        ) {
            $recentInvitesQuery->where('delivery_status', $inviteDeliveryStatus);
        }

        $recentInvites = $recentInvitesQuery
            ->limit(12)
            ->get()
            ->map(function (Invite $invite) {
                $status = 'pending';

                if ($invite->accepted_at) {
                    $status = 'accepted';
                } elseif ($invite->expires_at && $invite->expires_at->isPast()) {
                    $status = 'expired';
                }

                return [
                    'id' => $invite->id,
                    'email' => $invite->email,
                    'role' => $invite->role,
                    'company' => $invite->company?->name,
                    'status' => $status,
                    'delivery_status' => $invite->delivery_status,
                    'created_by' => $invite->creator?->name,
                    'created_at' => $invite->created_at?->toIso8601String(),
                ];
            });

        $today = CarbonImmutable::now()->startOfDay();
        $trendStart = $today->subDays($trendWindow - 1);
        $deliveryTrend = $this->buildDeliveryTrend($trendStart, $today);
        $deliverySummary = $this->deliverySummary($deliveryTrend, $trendWindow);

        $adminActionsQuery = $this->baseAdminActionsQuery();
        $this->applyAdminFilters($adminActionsQuery, $adminFilters);

        $recentAdminActions = $adminActionsQuery
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function (AuditLog $log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'record_type' => class_basename($log->auditable_type),
                    'record_id' => $log->auditable_id,
                    'company' => $log->company?->name,
                    'actor' => $log->actor?->name,
                    'created_at' => $log->created_at?->toIso8601String(),
                ];
            });

        $adminActionOptions = $this->baseAdminActionsQuery()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->values()
            ->all();

        $adminActorIds = $this->baseAdminActionsQuery()
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $adminActors = $adminActorIds->isNotEmpty()
            ? User::query()
                ->whereIn('id', $adminActorIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(function (User $user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                    ];
                })
                ->values()
                ->all()
            : [];

        return Inertia::render('platform/dashboard', [
            'stats' => $stats,
            'recentCompanies' => $recentCompanies,
            'recentInvites' => $recentInvites,
            'recentAdminActions' => $recentAdminActions,
            'deliverySummary' => $deliverySummary,
            'deliveryTrend' => $deliveryTrend,
            'operationsFilters' => [
                ...$adminFilters,
                'trend_window' => $trendWindow,
            ],
            'operationsTab' => $operationsTab,
            'adminFilterOptions' => [
                'actions' => $adminActionOptions,
                'actors' => $adminActors,
            ],
            'notificationGovernanceAnalytics' => $notificationGovernance->getAnalytics($trendWindow),
            'operationsReportPresets' => $operationsSettings->getPresets(),
            'dashboardPreferences' => $preferences,
        ]);
    }

    public function governance(
        Request $request,
        NotificationGovernanceService $notificationGovernance,
        OperationsReportingSettingsService $operationsSettings
    ): Response {
        $validatedFilters = $this->validatedOperationsFilters($request);
        $normalizedFilters = $operationsSettings->normalizeFilters($validatedFilters);
        $trendWindow = (int) ($normalizedFilters['trend_window'] ?? 30);

        return Inertia::render('platform/governance', [
            'analyticsFilters' => [
                'trend_window' => $trendWindow,
            ],
            'notificationGovernance' => $notificationGovernance->getSettings(),
            'notificationGovernanceAnalytics' => $notificationGovernance->getAnalytics($trendWindow),
            'operationsReportPresets' => $operationsSettings->getPresets(),
            'operationsReportDeliverySchedule' => $operationsSettings->getDeliverySchedule(),
        ]);
    }

    public function updateNotificationGovernance(
        Request $request,
        NotificationGovernanceService $notificationGovernance
    ): RedirectResponse {
        $data = $request->validate([
            'min_severity' => ['required', 'string', 'in:low,medium,high,critical'],
            'escalation_enabled' => ['required', 'boolean'],
            'escalation_severity' => ['required', 'string', 'in:low,medium,high,critical'],
            'escalation_delay_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'digest_enabled' => ['required', 'boolean'],
            'digest_frequency' => ['required', 'string', 'in:daily,weekly'],
            'digest_day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'digest_time' => ['required', 'date_format:H:i'],
            'digest_timezone' => ['required', 'string', 'max:64'],
        ]);

        $notificationGovernance->setSettings(
            data: $data,
            actorId: $request->user()?->id
        );

        return redirect()
            ->route('platform.governance')
            ->with('success', 'Notification governance controls updated.');
    }

    public function storeReportPreset(
        Request $request,
        OperationsReportingSettingsService $operationsSettings
    ): RedirectResponse {
        $redirectToReports = $request->routeIs('platform.reports.report-presets.store');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'trend_window' => ['nullable', 'integer', 'in:7,30,90'],
            'admin_action' => ['nullable', 'string', 'max:32'],
            'admin_actor_id' => ['nullable', 'uuid', 'exists:users,id'],
            'admin_start_date' => ['nullable', 'date_format:Y-m-d'],
            'admin_end_date' => ['nullable', 'date_format:Y-m-d'],
            'invite_delivery_status' => ['nullable', 'string', 'in:pending,sent,failed'],
        ]);

        $operationsSettings->savePreset(
            name: (string) $data['name'],
            filters: $data,
            actorId: $request->user()?->id
        );

        $targetRoute = $redirectToReports
            ? 'platform.reports'
            : 'platform.dashboard';

        return redirect()
            ->route($targetRoute)
            ->with('success', 'Operations report preset saved.');
    }

    public function destroyReportPreset(
        Request $request,
        string $presetId,
        OperationsReportingSettingsService $operationsSettings
    ): RedirectResponse {
        $redirectToReports = $request->routeIs('platform.reports.report-presets.destroy');

        $deleted = $operationsSettings->deletePreset(
            presetId: $presetId,
            actorId: $request->user()?->id
        );

        $targetRoute = $redirectToReports
            ? 'platform.reports'
            : 'platform.dashboard';

        return redirect()
            ->route($targetRoute)
            ->with($deleted ? 'success' : 'warning', $deleted
                ? 'Operations report preset deleted.'
                : 'Preset was not found.');
    }

    public function updatePreferences(
        Request $request,
        DashboardPreferencesService $dashboardPreferences,
        OperationsReportingSettingsService $operationsSettings
    ): RedirectResponse {
        $redirectToSettings = $request->routeIs('settings.dashboard-personalization.update');

        $data = $request->validate([
            'default_preset_id' => ['nullable', 'string', 'max:80'],
            'default_operations_tab' => ['required', 'string', 'in:companies,invites,admin_actions'],
            'layout' => ['required', 'string', 'in:balanced,analytics_first,operations_first'],
            'hidden_widgets' => ['nullable', 'array'],
            'hidden_widgets.*' => [
                'string',
                'in:delivery_performance,governance_snapshot,operations_presets,operations_detail',
            ],
        ]);

        $defaultPresetId = $data['default_preset_id'] ?? null;
        if (! is_string($defaultPresetId) || trim($defaultPresetId) === '') {
            $defaultPresetId = null;
        } else {
            $defaultPresetId = trim($defaultPresetId);
        }

        if (
            $defaultPresetId !== null
            && ! collect($operationsSettings->getPresets())->contains(
                fn (array $preset) => $preset['id'] === $defaultPresetId
            )
        ) {
            $redirect = $redirectToSettings
                ? redirect()->route('settings.dashboard-personalization.edit')
                : redirect()->route('platform.dashboard');

            return $redirect
                ->withErrors([
                    'default_preset_id' => 'Selected default preset was not found.',
                ]);
        }

        $preferences = $dashboardPreferences->set([
            'default_preset_id' => $defaultPresetId,
            'default_operations_tab' => $data['default_operations_tab'],
            'layout' => $data['layout'],
            'hidden_widgets' => $data['hidden_widgets'] ?? [],
        ], $request->user()?->id, $request->user()?->id);

        if ($redirectToSettings) {
            return redirect()
                ->route('settings.dashboard-personalization.edit')
                ->with('success', 'Dashboard preferences updated.');
        }

        return redirect()
            ->route('platform.dashboard', [
                'operations_tab' => $preferences['default_operations_tab'] ?? 'companies',
            ])
            ->with('success', 'Dashboard preferences updated.');
    }

    public function updateReportDeliverySchedule(
        Request $request,
        OperationsReportingSettingsService $operationsSettings
    ): RedirectResponse {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'preset_id' => ['nullable', 'string', 'max:80'],
            'format' => ['required', 'string', 'in:pdf,xlsx'],
            'frequency' => ['required', 'string', 'in:daily,weekly'],
            'day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        $presetId = $data['preset_id'] ?? null;
        $presetExists = $presetId === null
            || $presetId === ''
            || collect($operationsSettings->getPresets())->contains(
                fn (array $preset) => $preset['id'] === $presetId
            );

        if (! $presetExists) {
            return redirect()
                ->route('platform.governance')
                ->withErrors([
                    'delivery_schedule.preset_id' => 'Selected preset was not found.',
                ]);
        }

        $operationsSettings->setDeliverySchedule([
            'enabled' => (bool) $data['enabled'],
            'preset_id' => $presetId ?: null,
            'format' => $data['format'],
            'frequency' => $data['frequency'],
            'day_of_week' => (int) $data['day_of_week'],
            'time' => $data['time'],
            'timezone' => $data['timezone'],
        ], $request->user()?->id);

        return redirect()
            ->route('platform.governance')
            ->with('success', 'Operations report delivery schedule updated.');
    }

    public function exportAdminActions(
        Request $request,
        OperationsReportingSettingsService $operationsSettings,
        DashboardPreferencesService $dashboardPreferences,
        PlatformReportsService $reportsService,
        PlatformReportExportService $exportService
    ): HttpResponse {
        $validatedFilters = $this->validatedOperationsFilters($request);

        if (! $this->hasAnyOperationsFilterInput($request)) {
            $preferences = $dashboardPreferences->get($request->user()?->id);
            $validatedFilters = [
                ...$validatedFilters,
                ...$operationsSettings->filtersForPreset(
                    $preferences['default_preset_id'] ?? null
                ),
            ];
        }

        $filters = $operationsSettings->normalizeFilters($validatedFilters);
        $report = $reportsService->buildReport(
            PlatformReportsService::REPORT_ADMIN_ACTIONS,
            $filters
        );

        abort_if(! $report, 404);

        $format = $exportService->normalizeFormat(
            (string) $request->input('format', 'pdf')
        );

        return $exportService->export($report, $format);
    }

    public function exportDeliveryTrends(
        Request $request,
        OperationsReportingSettingsService $operationsSettings,
        DashboardPreferencesService $dashboardPreferences,
        PlatformReportsService $reportsService,
        PlatformReportExportService $exportService
    ): HttpResponse {
        $validatedFilters = $this->validatedOperationsFilters($request);

        if (! $this->hasAnyOperationsFilterInput($request)) {
            $preferences = $dashboardPreferences->get($request->user()?->id);
            $validatedFilters = [
                ...$validatedFilters,
                ...$operationsSettings->filtersForPreset(
                    $preferences['default_preset_id'] ?? null
                ),
            ];
        }

        $filters = $operationsSettings->normalizeFilters($validatedFilters);
        $report = $reportsService->buildReport(
            PlatformReportsService::REPORT_DELIVERY_TRENDS,
            $filters
        );

        abort_if(! $report, 404);

        $format = $exportService->normalizeFormat(
            (string) $request->input('format', 'pdf')
        );

        return $exportService->export($report, $format);
    }

    private function baseAdminActionsQuery(): Builder
    {
        return AuditLog::query()
            ->with(['actor:id,name,is_super_admin', 'company:id,name'])
            ->whereHas('actor', function ($query) {
                $query->where('is_super_admin', true);
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyAdminFilters(Builder $query, array $filters): void
    {
        $action = $filters['admin_action'] ?? null;
        $actorId = $filters['admin_actor_id'] ?? null;
        $startDate = $filters['admin_start_date'] ?? null;
        $endDate = $filters['admin_end_date'] ?? null;

        if ($action) {
            $query->where('action', $action);
        }

        if ($actorId) {
            $query->where('user_id', $actorId);
        }

        if (! $startDate && ! $endDate) {
            return;
        }

        $start = $startDate
            ? CarbonImmutable::createFromFormat('Y-m-d', $startDate)->startOfDay()
            : null;
        $end = $endDate
            ? CarbonImmutable::createFromFormat('Y-m-d', $endDate)->endOfDay()
            : null;

        if ($start && $end && $start->gt($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);

            return;
        }

        if ($start) {
            $query->where('created_at', '>=', $start);

            return;
        }

        if ($end) {
            $query->where('created_at', '<=', $end);
        }
    }

    /**
     * @return array<int, array{date: string, sent: int, failed: int, pending: int}>
     */
    private function buildDeliveryTrend(
        CarbonImmutable $startDate,
        CarbonImmutable $endDate
    ): array {
        $rows = [];

        for ($day = $startDate; $day->lte($endDate); $day = $day->addDay()) {
            $rows[$day->toDateString()] = [
                'date' => $day->toDateString(),
                'sent' => 0,
                'failed' => 0,
                'pending' => 0,
            ];
        }

        $invites = Invite::query()
            ->whereBetween('created_at', [$startDate, $endDate->endOfDay()])
            ->get(['delivery_status', 'created_at']);

        foreach ($invites as $invite) {
            $date = $invite->created_at?->toDateString();

            if (! $date || ! isset($rows[$date])) {
                continue;
            }

            $status = $this->normalizeDeliveryStatus($invite->delivery_status);
            $rows[$date][$status] += 1;
        }

        return array_values($rows);
    }

    /**
     * @param  array<int, array{date: string, sent: int, failed: int, pending: int}>  $trend
     * @return array{window_days: int, sent: int, failed: int, pending: int, total: int, failure_rate: float}
     */
    private function deliverySummary(array $trend, int $windowDays): array
    {
        $sent = (int) collect($trend)->sum('sent');
        $failed = (int) collect($trend)->sum('failed');
        $pending = (int) collect($trend)->sum('pending');
        $attempted = $sent + $failed;
        $failureRate = $attempted > 0
            ? round(($failed / $attempted) * 100, 2)
            : 0.0;

        return [
            'window_days' => $windowDays,
            'sent' => $sent,
            'failed' => $failed,
            'pending' => $pending,
            'total' => $sent + $failed + $pending,
            'failure_rate' => $failureRate,
        ];
    }

    private function normalizeDeliveryStatus(?string $status): string
    {
        if ($status === Invite::DELIVERY_SENT) {
            return 'sent';
        }

        if ($status === Invite::DELIVERY_FAILED) {
            return 'failed';
        }

        return 'pending';
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedOperationsFilters(Request $request): array
    {
        return $request->validate([
            'trend_window' => ['nullable', 'integer', 'in:7,30,90'],
            'admin_action' => ['nullable', 'string', 'max:32'],
            'admin_actor_id' => ['nullable', 'uuid', 'exists:users,id'],
            'admin_start_date' => ['nullable', 'date_format:Y-m-d'],
            'admin_end_date' => ['nullable', 'date_format:Y-m-d'],
            'invite_delivery_status' => ['nullable', 'string', 'in:pending,sent,failed'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validatedFilters
     * @return array<string, mixed>
     */
    private function normalizedAdminFilters(array $validatedFilters): array
    {
        return [
            'admin_action' => $validatedFilters['admin_action'] ?? null,
            'admin_actor_id' => $validatedFilters['admin_actor_id'] ?? null,
            'admin_start_date' => $validatedFilters['admin_start_date'] ?? null,
            'admin_end_date' => $validatedFilters['admin_end_date'] ?? null,
            'invite_delivery_status' => $validatedFilters['invite_delivery_status'] ?? null,
        ];
    }

    private function hasAnyOperationsFilterInput(Request $request): bool
    {
        foreach ([
            'trend_window',
            'admin_action',
            'admin_actor_id',
            'admin_start_date',
            'admin_end_date',
            'invite_delivery_status',
        ] as $key) {
            if ($request->filled($key)) {
                return true;
            }
        }

        return false;
    }

    private function normalizedOperationsTab(mixed $tab): string
    {
        if (! is_string($tab)) {
            return 'companies';
        }

        $normalized = strtolower(trim($tab));

        if (! in_array($normalized, ['companies', 'invites', 'admin_actions'], true)) {
            return 'companies';
        }

        return $normalized;
    }

}
