<?php

namespace App\Http\Controllers\Platform;

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

        return Inertia::render('platform/dashboard', [
            'stats' => $stats,
            'recentCompanies' => $recentCompanies,
        ]);
    }
}
