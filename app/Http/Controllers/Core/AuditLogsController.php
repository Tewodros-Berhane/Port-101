<?php

namespace App\Http\Controllers\Core;

use App\Core\Audit\Models\AuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AuditLog::class);

        $logs = AuditLog::query()
            ->with('actor:id,name')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('core/audit-logs/index', [
            'logs' => $logs->through(function (AuditLog $log) {
                $changes = $log->changes ?? [];
                $before = is_array($changes['before'] ?? null)
                    ? $changes['before']
                    : [];
                $after = is_array($changes['after'] ?? null)
                    ? $changes['after']
                    : [];
                $changeKeys = array_values(
                    array_unique(array_merge(array_keys($before), array_keys($after)))
                );

                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'record_type' => class_basename($log->auditable_type),
                    'record_id' => $log->auditable_id,
                    'actor' => $log->actor?->name,
                    'created_at' => $log->created_at?->toIso8601String(),
                    'change_keys' => $changeKeys,
                ];
            }),
        ]);
    }
}
