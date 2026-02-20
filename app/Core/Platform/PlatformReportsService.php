<?php

namespace App\Core\Platform;

use App\Core\Access\Models\Invite;
use App\Core\Audit\Models\AuditLog;
use App\Core\Company\Models\Company;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;

class PlatformReportsService
{
    public const REPORT_ADMIN_ACTIONS = 'admin-actions';

    public const REPORT_DELIVERY_TRENDS = 'invite-delivery-trends';

    public const REPORT_COMPANIES = 'companies';

    public const REPORT_PLATFORM_ADMINS = 'platform-admins';

    public const REPORT_PLATFORM_INVITES = 'platform-invites';

    public const REPORT_NOTIFICATION_EVENTS = 'notification-events';

    public const REPORT_PLATFORM_PERFORMANCE = 'platform-performance';

    /**
     * @return array<int, array{
     *  key: string,
     *  title: string,
     *  description: string,
     *  row_count: int
     * }>
     */
    public function reportCatalog(array $filters): array
    {
        return [
            [
                'key' => self::REPORT_ADMIN_ACTIONS,
                'title' => 'Admin actions',
                'description' => 'Superadmin audit activity, actor attribution, and action volume.',
                'row_count' => $this->adminActionsCount($filters),
            ],
            [
                'key' => self::REPORT_DELIVERY_TRENDS,
                'title' => 'Invite delivery trends',
                'description' => 'Daily sent/failed/pending invite delivery performance.',
                'row_count' => $this->deliveryInvitesCount($filters),
            ],
            [
                'key' => self::REPORT_COMPANIES,
                'title' => 'Company registry',
                'description' => 'Company lifecycle, activation status, owner, and creation timestamps.',
                'row_count' => $this->companiesCount($filters),
            ],
            [
                'key' => self::REPORT_PLATFORM_ADMINS,
                'title' => 'Platform admins',
                'description' => 'Privileged user inventory and account coverage.',
                'row_count' => $this->platformAdminsCount($filters),
            ],
            [
                'key' => self::REPORT_PLATFORM_INVITES,
                'title' => 'Platform invites',
                'description' => 'Platform-issued invite status, delivery outcomes, and expiry tracking.',
                'row_count' => $this->platformInvitesCount($filters),
            ],
            [
                'key' => self::REPORT_NOTIFICATION_EVENTS,
                'title' => 'Notification events',
                'description' => 'Severity mix, read coverage, and event source visibility for platform admins.',
                'row_count' => $this->notificationEventsCount($filters),
            ],
            [
                'key' => self::REPORT_PLATFORM_PERFORMANCE,
                'title' => 'Platform performance snapshot',
                'description' => 'Core platform KPIs (companies, users, invites, audit volume, notifications).',
                'row_count' => count($this->performanceMetricsRows()),
            ],
        ];
    }

    /**
     * @return array{
     *  key: string,
     *  title: string,
     *  subtitle: string,
     *  columns: array<int, string>,
     *  rows: array<int, array<int, string|int|float>>
     * }|null
     */
    public function buildReport(string $reportKey, array $filters): ?array
    {
        return match ($reportKey) {
            self::REPORT_ADMIN_ACTIONS => $this->adminActionsReport($filters),
            self::REPORT_DELIVERY_TRENDS => $this->deliveryTrendsReport($filters),
            self::REPORT_COMPANIES => $this->companiesReport($filters),
            self::REPORT_PLATFORM_ADMINS => $this->platformAdminsReport($filters),
            self::REPORT_PLATFORM_INVITES => $this->platformInvitesReport($filters),
            self::REPORT_NOTIFICATION_EVENTS => $this->notificationEventsReport($filters),
            self::REPORT_PLATFORM_PERFORMANCE => $this->platformPerformanceReport(),
            default => null,
        };
    }

    /**
     * @return array{
     *  actions: array<int, string>,
     *  actors: array<int, array{id: string, name: string}>
     * }
     */
    public function adminFilterOptions(): array
    {
        $actions = $this->baseAdminActionsQuery()
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

        $actors = $adminActorIds->isNotEmpty()
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

        return [
            'actions' => $actions,
            'actors' => $actors,
        ];
    }

    private function adminActionsCount(array $filters): int
    {
        $query = $this->baseAdminActionsQuery();
        $this->applyAdminFilters($query, $filters);

        return $query->count();
    }

    private function deliveryInvitesCount(array $filters): int
    {
        [$start, $end] = $this->trendWindowRange((int) ($filters['trend_window'] ?? 30));
        $query = Invite::query()->whereBetween('created_at', [$start, $end->endOfDay()]);
        $this->applyInviteDeliveryFilter($query, $filters['invite_delivery_status'] ?? null);

        return $query->count();
    }

    private function companiesCount(array $filters): int
    {
        $query = Company::query();
        $this->applyDateRange(
            $query,
            $filters['admin_start_date'] ?? null,
            $filters['admin_end_date'] ?? null
        );

        return $query->count();
    }

    private function platformAdminsCount(array $filters): int
    {
        $query = User::query()->where('is_super_admin', true);
        $this->applyDateRange(
            $query,
            $filters['admin_start_date'] ?? null,
            $filters['admin_end_date'] ?? null
        );

        return $query->count();
    }

    private function platformInvitesCount(array $filters): int
    {
        $query = Invite::query()->whereNull('company_id');
        $this->applyDateRange(
            $query,
            $filters['admin_start_date'] ?? null,
            $filters['admin_end_date'] ?? null
        );
        $this->applyInviteDeliveryFilter($query, $filters['invite_delivery_status'] ?? null);

        return $query->count();
    }

    private function notificationEventsCount(array $filters): int
    {
        $query = $this->baseNotificationEventsQuery();
        $this->applyDateRange(
            $query,
            $filters['admin_start_date'] ?? null,
            $filters['admin_end_date'] ?? null
        );

        return $query->count();
    }

    /**
     * @return array{
     *  key: string,
     *  title: string,
     *  subtitle: string,
     *  columns: array<int, string>,
     *  rows: array<int, array<int, string|int|float>>
     * }
     */
    private function adminActionsReport(array $filters): array
    {
        $query = $this->baseAdminActionsQuery();
        $this->applyAdminFilters($query, $filters);

        $rows = $query
            ->orderByDesc('created_at')
            ->get()
            ->map(function (AuditLog $log) {
                return [
                    $this->formatDateTime($log->created_at?->toIso8601String()),
                    $log->action,
                    class_basename((string) $log->auditable_type),
                    (string) $log->auditable_id,
                    $log->company?->name ?? 'Platform',
                    $log->actor?->name ?? 'System',
                ];
            })
            ->values()
            ->all();

        return [
            'key' => self::REPORT_ADMIN_ACTIONS,
            'title' => 'Admin Actions Report',
            'subtitle' => $this->reportSubtitle($filters),
            'columns' => [
                'Created At',
                'Action',
                'Record Type',
                'Record ID',
                'Company',
                'Actor',
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{
     *  key: string,
     *  title: string,
     *  subtitle: string,
     *  columns: array<int, string>,
     *  rows: array<int, array<int, string|int|float>>
     * }
     */
    private function deliveryTrendsReport(array $filters): array
    {
        $trendWindow = (int) ($filters['trend_window'] ?? 30);
        [$start, $end] = $this->trendWindowRange($trendWindow);
        $rows = $this->buildDeliveryTrend($start, $end, $filters);

        $mappedRows = collect($rows)
            ->map(function (array $row) {
                $total = (int) ($row['sent'] ?? 0)
                    + (int) ($row['failed'] ?? 0)
                    + (int) ($row['pending'] ?? 0);

                return [
                    (string) ($row['date'] ?? ''),
                    (int) ($row['sent'] ?? 0),
                    (int) ($row['failed'] ?? 0),
                    (int) ($row['pending'] ?? 0),
                    $total,
                ];
            })
            ->values()
            ->all();

        return [
            'key' => self::REPORT_DELIVERY_TRENDS,
            'title' => 'Invite Delivery Trends Report',
            'subtitle' => "Trend window: {$trendWindow} days",
            'columns' => [
                'Date',
                'Sent',
                'Failed',
                'Pending',
                'Total',
            ],
            'rows' => $mappedRows,
        ];
    }

    /**
     * @return array{
     *  key: string,
     *  title: string,
     *  subtitle: string,
     *  columns: array<int, string>,
     *  rows: array<int, array<int, string|int|float>>
     * }
     */
    private function companiesReport(array $filters): array
    {
        $query = Company::query()
            ->with('owner:id,name,email')
            ->orderByDesc('created_at');

        $this->applyDateRange(
            $query,
            $filters['admin_start_date'] ?? null,
            $filters['admin_end_date'] ?? null
        );

        $rows = $query
            ->get()
            ->map(function (Company $company) {
                return [
                    $company->name,
                    $company->slug,
                    $company->is_active ? 'Active' : 'Inactive',
                    (string) $company->timezone,
                    (string) $company->currency_code,
                    $company->owner?->name ?? '-',
                    $this->formatDateTime($company->created_at?->toIso8601String()),
                ];
            })
            ->values()
            ->all();

        return [
            'key' => self::REPORT_COMPANIES,
            'title' => 'Company Registry Report',
            'subtitle' => $this->reportSubtitle($filters),
            'columns' => [
                'Name',
                'Slug',
                'Status',
                'Timezone',
                'Currency',
                'Owner',
                'Created At',
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{
     *  key: string,
     *  title: string,
     *  subtitle: string,
     *  columns: array<int, string>,
     *  rows: array<int, array<int, string|int|float>>
     * }
     */
    private function platformAdminsReport(array $filters): array
    {
        $query = User::query()
            ->where('is_super_admin', true)
            ->with('currentCompany:id,name')
            ->orderBy('name');

        $this->applyDateRange(
            $query,
            $filters['admin_start_date'] ?? null,
            $filters['admin_end_date'] ?? null
        );

        $rows = $query
            ->get()
            ->map(function (User $user) {
                return [
                    $user->name,
                    $user->email,
                    $user->currentCompany?->name ?? '-',
                    $this->formatDateTime($user->created_at?->toIso8601String()),
                ];
            })
            ->values()
            ->all();

        return [
            'key' => self::REPORT_PLATFORM_ADMINS,
            'title' => 'Platform Admins Report',
            'subtitle' => $this->reportSubtitle($filters),
            'columns' => [
                'Name',
                'Email',
                'Current Company',
                'Created At',
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{
     *  key: string,
     *  title: string,
     *  subtitle: string,
     *  columns: array<int, string>,
     *  rows: array<int, array<int, string|int|float>>
     * }
     */
    private function platformInvitesReport(array $filters): array
    {
        $query = Invite::query()
            ->whereNull('company_id')
            ->with('creator:id,name')
            ->orderByDesc('created_at');

        $this->applyDateRange(
            $query,
            $filters['admin_start_date'] ?? null,
            $filters['admin_end_date'] ?? null
        );
        $this->applyInviteDeliveryFilter($query, $filters['invite_delivery_status'] ?? null);

        $rows = $query
            ->get()
            ->map(function (Invite $invite) {
                return [
                    $invite->email,
                    $invite->role,
                    $this->inviteLifecycleStatus($invite),
                    $invite->delivery_status ?? Invite::DELIVERY_PENDING,
                    $invite->creator?->name ?? 'System',
                    $this->formatDateTime($invite->created_at?->toIso8601String()),
                    $this->formatDateTime($invite->expires_at?->toIso8601String()),
                ];
            })
            ->values()
            ->all();

        return [
            'key' => self::REPORT_PLATFORM_INVITES,
            'title' => 'Platform Invites Report',
            'subtitle' => $this->reportSubtitle($filters),
            'columns' => [
                'Email',
                'Role',
                'Status',
                'Delivery',
                'Created By',
                'Created At',
                'Expires At',
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{
     *  key: string,
     *  title: string,
     *  subtitle: string,
     *  columns: array<int, string>,
     *  rows: array<int, array<int, string|int|float>>
     * }
     */
    private function notificationEventsReport(array $filters): array
    {
        $query = $this->baseNotificationEventsQuery()
            ->orderByDesc('created_at');

        $this->applyDateRange(
            $query,
            $filters['admin_start_date'] ?? null,
            $filters['admin_end_date'] ?? null
        );

        $adminNames = User::query()
            ->where('is_super_admin', true)
            ->pluck('name', 'id');

        $rows = $query
            ->get()
            ->map(function (DatabaseNotification $notification) use ($adminNames) {
                $payload = is_array($notification->data)
                    ? $notification->data
                    : [];
                $meta = is_array($payload['meta'] ?? null)
                    ? $payload['meta']
                    : [];

                return [
                    $this->formatDateTime($notification->created_at?->toIso8601String()),
                    $adminNames->get((string) $notification->notifiable_id, 'Unknown'),
                    (string) ($payload['title'] ?? 'Notification'),
                    (string) ($payload['severity'] ?? 'low'),
                    $notification->read_at ? 'Yes' : 'No',
                    (string) ($meta['source'] ?? 'n/a'),
                ];
            })
            ->values()
            ->all();

        return [
            'key' => self::REPORT_NOTIFICATION_EVENTS,
            'title' => 'Notification Events Report',
            'subtitle' => $this->reportSubtitle($filters),
            'columns' => [
                'Created At',
                'Recipient',
                'Title',
                'Severity',
                'Read',
                'Source',
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{
     *  key: string,
     *  title: string,
     *  subtitle: string,
     *  columns: array<int, string>,
     *  rows: array<int, array<int, string|int|float>>
     * }
     */
    private function platformPerformanceReport(): array
    {
        return [
            'key' => self::REPORT_PLATFORM_PERFORMANCE,
            'title' => 'Platform Performance Snapshot',
            'subtitle' => 'Current KPI snapshot',
            'columns' => ['Metric', 'Value'],
            'rows' => $this->performanceMetricsRows(),
        ];
    }

    /**
     * @return array<int, array<int, string|int|float>>
     */
    private function performanceMetricsRows(): array
    {
        $totalCompanies = Company::query()->count();
        $activeCompanies = Company::query()->where('is_active', true)->count();
        $totalUsers = User::query()->count();
        $superAdmins = User::query()->where('is_super_admin', true)->count();
        $totalInvites = Invite::query()->count();
        $acceptedInvites = Invite::query()->whereNotNull('accepted_at')->count();
        $auditLogs = AuditLog::query()->count();
        $notifications30d = DatabaseNotification::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return [
            ['Total companies', $totalCompanies],
            ['Active companies', $activeCompanies],
            ['Inactive companies', max($totalCompanies - $activeCompanies, 0)],
            ['Total users', $totalUsers],
            ['Platform admins', $superAdmins],
            ['Company users', max($totalUsers - $superAdmins, 0)],
            ['Total invites', $totalInvites],
            ['Accepted invites', $acceptedInvites],
            ['Pending invites', max($totalInvites - $acceptedInvites, 0)],
            ['Audit log records', $auditLogs],
            ['Notifications (last 30 days)', $notifications30d],
        ];
    }

    private function inviteLifecycleStatus(Invite $invite): string
    {
        if ($invite->accepted_at) {
            return 'accepted';
        }

        if ($invite->expires_at && $invite->expires_at->isPast()) {
            return 'expired';
        }

        return 'pending';
    }

    private function reportSubtitle(array $filters): string
    {
        $parts = [];

        if (! empty($filters['admin_start_date']) || ! empty($filters['admin_end_date'])) {
            $parts[] = sprintf(
                'Date range: %s to %s',
                (string) ($filters['admin_start_date'] ?? 'start'),
                (string) ($filters['admin_end_date'] ?? 'end')
            );
        }

        if (! empty($filters['admin_action'])) {
            $parts[] = 'Action: '.(string) $filters['admin_action'];
        }

        if (! empty($filters['invite_delivery_status'])) {
            $parts[] = 'Invite delivery: '.(string) $filters['invite_delivery_status'];
        }

        if (empty($parts)) {
            return 'Filters: default';
        }

        return 'Filters: '.implode(' | ', $parts);
    }

    private function formatDateTime(?string $value): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return CarbonImmutable::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function trendWindowRange(int $window): array
    {
        $trendWindow = in_array($window, [7, 30, 90], true)
            ? $window
            : 30;

        $today = CarbonImmutable::now()->startOfDay();

        return [
            $today->subDays($trendWindow - 1),
            $today,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyAdminFilters(Builder $query, array $filters): void
    {
        $action = $filters['admin_action'] ?? null;
        $actorId = $filters['admin_actor_id'] ?? null;

        if ($action) {
            $query->where('action', $action);
        }

        if ($actorId) {
            $query->where('user_id', $actorId);
        }

        $this->applyDateRange(
            $query,
            $filters['admin_start_date'] ?? null,
            $filters['admin_end_date'] ?? null
        );
    }

    private function applyInviteDeliveryFilter(Builder $query, mixed $status): void
    {
        if (! is_string($status) || trim($status) === '') {
            return;
        }

        $normalized = strtolower(trim($status));

        if ($normalized === Invite::DELIVERY_PENDING) {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('delivery_status')
                    ->orWhere('delivery_status', Invite::DELIVERY_PENDING);
            });

            return;
        }

        if (in_array($normalized, [Invite::DELIVERY_SENT, Invite::DELIVERY_FAILED], true)) {
            $query->where('delivery_status', $normalized);
        }
    }

    private function applyDateRange(
        Builder $query,
        mixed $startDate,
        mixed $endDate
    ): void {
        $start = $this->parseDate($startDate, true);
        $end = $this->parseDate($endDate, false);

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

    private function parseDate(mixed $value, bool $startOfDay): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('Y-m-d', trim($value));

            return $startOfDay
                ? $parsed->startOfDay()
                : $parsed->endOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array{date: string, sent: int, failed: int, pending: int}>
     */
    private function buildDeliveryTrend(
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        array $filters
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

        $invitesQuery = Invite::query()
            ->whereBetween('created_at', [$startDate, $endDate->endOfDay()]);

        $this->applyInviteDeliveryFilter($invitesQuery, $filters['invite_delivery_status'] ?? null);

        $invites = $invitesQuery->get(['delivery_status', 'created_at']);

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

    private function baseAdminActionsQuery(): Builder
    {
        return AuditLog::query()
            ->with(['actor:id,name,is_super_admin', 'company:id,name'])
            ->whereHas('actor', function ($query) {
                $query->where('is_super_admin', true);
            });
    }

    private function baseNotificationEventsQuery(): Builder
    {
        $superAdminIds = User::query()
            ->where('is_super_admin', true)
            ->pluck('id')
            ->all();

        return DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $superAdminIds);
    }
}
