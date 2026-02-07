<?php

namespace App\Http\Controllers\Platform;

use App\Core\Access\Models\Invite;
use App\Core\Audit\Models\AuditLog;
use App\Core\Company\Models\Company;
use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
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

        $recentAdminActions = AuditLog::query()
            ->with(['actor:id,name,is_super_admin', 'company:id,name'])
            ->whereHas('actor', function ($query) {
                $query->where('is_super_admin', true);
            })
            ->orderByDesc('created_at')
            ->limit(8)
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

        return Inertia::render('platform/dashboard', [
            'stats' => $stats,
            'recentCompanies' => $recentCompanies,
            'recentInvites' => $recentInvites,
            'recentAdminActions' => $recentAdminActions,
        ]);
    }
}
