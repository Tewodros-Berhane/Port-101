<?php

namespace App\Http\Controllers\Platform;

use App\Core\Access\Models\Invite;
use App\Core\Audit\Models\AuditLog;
use App\Core\Company\Models\Company;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $validatedFilters = $request->validate([
            'trend_window' => ['nullable', 'integer', 'in:7,30,90'],
            'admin_action' => ['nullable', 'string', 'max:32'],
            'admin_actor_id' => ['nullable', 'uuid', 'exists:users,id'],
            'admin_start_date' => ['nullable', 'date_format:Y-m-d'],
            'admin_end_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $trendWindow = (int) ($validatedFilters['trend_window'] ?? 30);
        $adminFilters = [
            'admin_action' => $validatedFilters['admin_action'] ?? null,
            'admin_actor_id' => $validatedFilters['admin_actor_id'] ?? null,
            'admin_start_date' => $validatedFilters['admin_start_date'] ?? null,
            'admin_end_date' => $validatedFilters['admin_end_date'] ?? null,
        ];

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

        $recentInvites = Invite::query()
            ->with(['company:id,name', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->limit(6)
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
            'adminFilterOptions' => [
                'actions' => $adminActionOptions,
                'actors' => $adminActors,
            ],
        ]);
    }

    private function baseAdminActionsQuery(): Builder
    {
        return AuditLog::query()
            ->with(['actor:id,name,is_super_admin', 'company:id,name'])
            ->whereHas('actor', function ($query) {
                $query->where('is_super_admin', true);
            });
    }

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
}
