<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Reports\Models\ReportExport;

class ReportExportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('reports.export')
            && (bool) $user->current_company_id;
    }

    public function view(User $user, ReportExport $reportExport): bool
    {
        return $user->hasPermission('reports.export')
            && $user->canAccessDataScopedRecord($reportExport);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('reports.export')
            && (bool) $user->current_company_id;
    }

    public function download(User $user, ReportExport $reportExport): bool
    {
        return $user->hasPermission('reports.export')
            && $user->canAccessDataScopedRecord($reportExport);
    }
}
