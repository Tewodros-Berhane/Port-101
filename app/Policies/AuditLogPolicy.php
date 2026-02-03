<?php

namespace App\Policies;

use App\Core\Audit\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('core.audit_logs.view');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->hasPermission('core.audit_logs.view');
    }
}
