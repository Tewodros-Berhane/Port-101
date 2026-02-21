<?php

namespace App\Http\Controllers\Company;

use App\Core\Access\Models\Invite;
use App\Core\Audit\Models\AuditLog;
use App\Core\Company\Models\CompanyUser;
use App\Core\MasterData\Models\Address;
use App\Core\MasterData\Models\Contact;
use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\PriceList;
use App\Core\MasterData\Models\Product;
use App\Core\MasterData\Models\Tax;
use App\Core\MasterData\Models\Uom;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $company = $user?->currentCompany;

        abort_unless($company, 403, 'Company context not available.');

        $companyId = $company->id;
        $today = CarbonImmutable::now()->startOfDay();
        $trendStart = $today->subDays(13);

        $teamMembers = CompanyUser::query()
            ->where('company_id', $companyId)
            ->count();

        $ownerCount = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('is_owner', true)
            ->count();

        $pendingInvites = Invite::query()
            ->where('company_id', $companyId)
            ->whereNull('accepted_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->count();

        $failedInviteDeliveries = Invite::query()
            ->where('company_id', $companyId)
            ->whereNull('accepted_at')
            ->where('delivery_status', Invite::DELIVERY_FAILED)
            ->count();

        $partners = Partner::query()->where('company_id', $companyId)->count();
        $contacts = Contact::query()->where('company_id', $companyId)->count();
        $addresses = Address::query()->where('company_id', $companyId)->count();
        $products = Product::query()->where('company_id', $companyId)->count();
        $taxes = Tax::query()->where('company_id', $companyId)->count();
        $currencies = Currency::query()->where('company_id', $companyId)->count();
        $uoms = Uom::query()->where('company_id', $companyId)->count();
        $priceLists = PriceList::query()->where('company_id', $companyId)->count();
        $masterDataRecords = $partners + $contacts + $addresses + $products + $taxes + $currencies + $uoms + $priceLists;

        $activityEventsCurrentWindow = AuditLog::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [
                $today->subDays(6)->startOfDay(),
                $today->endOfDay(),
            ])
            ->count();

        $activityEventsPreviousWindow = AuditLog::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [
                $today->subDays(13)->startOfDay(),
                $today->subDays(7)->endOfDay(),
            ])
            ->count();

        $invitesCreatedCurrentWindow = Invite::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [
                $today->subDays(6)->startOfDay(),
                $today->endOfDay(),
            ])
            ->count();

        $invitesCreatedPreviousWindow = Invite::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [
                $today->subDays(13)->startOfDay(),
                $today->subDays(7)->endOfDay(),
            ])
            ->count();

        $recentActivity = AuditLog::query()
            ->with('actor:id,name')
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(function (AuditLog $log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'record_type' => class_basename($log->auditable_type),
                    'actor' => $log->actor?->name,
                    'created_at' => $log->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('company/dashboard', [
            'companySummary' => [
                'name' => $company->name,
                'timezone' => $company->timezone,
                'currency_code' => $company->currency_code,
            ],
            'kpis' => [
                'team_members' => $teamMembers,
                'owners' => $ownerCount,
                'pending_invites' => $pendingInvites,
                'failed_invite_deliveries' => $failedInviteDeliveries,
                'master_data_records' => $masterDataRecords,
                'activity_events_7d' => $activityEventsCurrentWindow,
                'activity_events_change_pct' => $this->calculateChangePct(
                    $activityEventsCurrentWindow,
                    $activityEventsPreviousWindow
                ),
                'invites_created_7d' => $invitesCreatedCurrentWindow,
                'invites_created_change_pct' => $this->calculateChangePct(
                    $invitesCreatedCurrentWindow,
                    $invitesCreatedPreviousWindow
                ),
            ],
            'activityTrend' => $this->buildActivityTrend(
                $companyId,
                $trendStart,
                $today
            ),
            'inviteStatusMix' => $this->inviteStatusMix($companyId),
            'masterDataBreakdown' => [
                ['label' => 'Partners', 'value' => $partners],
                ['label' => 'Contacts', 'value' => $contacts],
                ['label' => 'Addresses', 'value' => $addresses],
                ['label' => 'Products', 'value' => $products],
                ['label' => 'Taxes', 'value' => $taxes],
                ['label' => 'Currencies', 'value' => $currencies],
                ['label' => 'Units', 'value' => $uoms],
                ['label' => 'Price lists', 'value' => $priceLists],
            ],
            'recentActivity' => $recentActivity,
        ]);
    }

    private function calculateChangePct(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return array<int, array{date: string, audits: int, invites: int}>
     */
    private function buildActivityTrend(
        string $companyId,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate
    ): array {
        $rows = [];

        for ($day = $startDate; $day->lte($endDate); $day = $day->addDay()) {
            $rows[$day->toDateString()] = [
                'date' => $day->toDateString(),
                'audits' => 0,
                'invites' => 0,
            ];
        }

        $auditRows = AuditLog::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->groupBy('date')
            ->get();

        foreach ($auditRows as $row) {
            $date = (string) $row->date;

            if (isset($rows[$date])) {
                $rows[$date]['audits'] = (int) $row->total;
            }
        }

        $inviteRows = Invite::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->groupBy('date')
            ->get();

        foreach ($inviteRows as $row) {
            $date = (string) $row->date;

            if (isset($rows[$date])) {
                $rows[$date]['invites'] = (int) $row->total;
            }
        }

        return array_values($rows);
    }

    /**
     * @return array{pending: int, accepted: int, expired: int, total: int}
     */
    private function inviteStatusMix(string $companyId): array
    {
        $accepted = Invite::query()
            ->where('company_id', $companyId)
            ->whereNotNull('accepted_at')
            ->count();

        $expired = Invite::query()
            ->where('company_id', $companyId)
            ->whereNull('accepted_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();

        $pending = Invite::query()
            ->where('company_id', $companyId)
            ->whereNull('accepted_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->count();

        return [
            'pending' => $pending,
            'accepted' => $accepted,
            'expired' => $expired,
            'total' => $pending + $accepted + $expired,
        ];
    }
}
