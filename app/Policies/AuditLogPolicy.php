<?php

namespace App\Policies;

use App\Core\Audit\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    private function inCompanyWorkspace(User $user, ?AuditLog $auditLog = null): bool
    {
        if ($user->is_super_admin || ! $user->current_company_id) {
            return false;
        }

        return ! $auditLog
            || (string) $auditLog->company_id === (string) $user->current_company_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('core.audit_logs.view')
            && $this->inCompanyWorkspace($user);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->hasPermission('core.audit_logs.view')
            && $this->inCompanyWorkspace($user, $auditLog);
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('core.audit_logs.manage')
            && $this->inCompanyWorkspace($user);
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return $user->hasPermission('core.audit_logs.manage')
            && $this->inCompanyWorkspace($user, $auditLog);
    }
}
