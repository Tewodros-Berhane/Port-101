<?php

namespace App\Http\Controllers\Core;

use App\Core\Audit\Models\AuditLog;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AuditLog::class);

        $filters = $this->filtersFromRequest($request);
        $logs = $this->filteredLogs($request)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();

        $recordTypes = AuditLog::query()
            ->select('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->get()
            ->map(function (AuditLog $log) {
                return [
                    'value' => $log->auditable_type,
                    'label' => class_basename($log->auditable_type),
                ];
            })
            ->values()
            ->all();

        $actorIds = AuditLog::query()
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $actors = $actorIds->isNotEmpty()
            ? User::query()
                ->whereIn('id', $actorIds)
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

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
            'filters' => $filters,
            'actions' => $actions,
            'recordTypes' => $recordTypes,
            'actors' => $actors,
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('export', AuditLog::class);

        $format = strtolower($request->input('format', 'csv'));

        if (! in_array($format, ['csv', 'json'], true)) {
            $format = 'csv';
        }

        $logs = $this->filteredLogs($request)
            ->orderByDesc('created_at')
            ->get();

        $payload = $logs->map(function (AuditLog $log) {
            return [
                'created_at' => $log->created_at?->toIso8601String(),
                'action' => $log->action,
                'record_type' => class_basename($log->auditable_type),
                'record_id' => $log->auditable_id,
                'actor' => $log->actor?->name,
                'changes' => $log->changes ?? [],
            ];
        });

        $timestamp = now()->format('Ymd-His');

        if ($format === 'json') {
            $filename = "audit-logs-{$timestamp}.json";

            return response()->json($payload)->withHeaders([
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        $filename = "audit-logs-{$timestamp}.csv";

        return response()->streamDownload(function () use ($payload) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Created At',
                'Action',
                'Record Type',
                'Record ID',
                'Actor',
                'Changes',
            ]);

            foreach ($payload as $row) {
                fputcsv($handle, [
                    $row['created_at'] ?? '',
                    $row['action'] ?? '',
                    $row['record_type'] ?? '',
                    $row['record_id'] ?? '',
                    $row['actor'] ?? '',
                    json_encode($row['changes'] ?? [], JSON_UNESCAPED_SLASHES),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function destroy(AuditLog $auditLog): RedirectResponse
    {
        $this->authorize('delete', $auditLog);

        $auditLog->delete();

        return redirect()
            ->route('core.audit-logs.index')
            ->with('success', 'Audit log entry removed.');
    }

    private function filteredLogs(Request $request): Builder
    {
        $query = AuditLog::query()->with('actor:id,name');

        $action = $request->input('action');
        $recordType = $request->input('record_type');
        $actorId = $request->input('actor_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if ($action) {
            $query->where('action', $action);
        }

        if ($recordType) {
            $query->where('auditable_type', $recordType);
        }

        if ($actorId) {
            $query->where('user_id', $actorId);
        }

        $this->applyDateFilters($query, $startDate, $endDate);

        return $query;
    }

    private function applyDateFilters(
        Builder $query,
        ?string $startDate,
        ?string $endDate
    ): void {
        if (! $startDate && ! $endDate) {
            return;
        }

        try {
            $start = $startDate ? Carbon::parse($startDate)->startOfDay() : null;
            $end = $endDate ? Carbon::parse($endDate)->endOfDay() : null;

            if ($start && $end) {
                $query->whereBetween('created_at', [$start, $end]);
            } elseif ($start) {
                $query->where('created_at', '>=', $start);
            } elseif ($end) {
                $query->where('created_at', '<=', $end);
            }
        } catch (\Throwable) {
            return;
        }
    }

    private function filtersFromRequest(Request $request): array
    {
        return [
            'action' => $request->input('action'),
            'record_type' => $request->input('record_type'),
            'actor_id' => $request->input('actor_id'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ];
    }
}
