<?php

namespace App\Core\Support;

use App\Core\Audit\Models\AuditLog;
use App\Core\Support\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            static::recordAudit($model, 'created', [
                'after' => static::filterAuditAttributes(
                    $model,
                    $model->getAttributes()
                ),
            ]);
        });

        static::updated(function (Model $model) {
            $changes = static::filterAuditAttributes($model, $model->getChanges());

            if ($changes === []) {
                return;
            }

            $before = [];

            foreach (array_keys($changes) as $key) {
                $before[$key] = $model->getOriginal($key);
            }

            static::recordAudit($model, 'updated', [
                'before' => $before,
                'after' => $changes,
            ]);
        });

        static::deleted(function (Model $model) {
            static::recordAudit($model, 'deleted', [
                'before' => static::filterAuditAttributes(
                    $model,
                    $model->getAttributes()
                ),
            ]);
        });

        static::restored(function (Model $model) {
            static::recordAudit($model, 'restored', [
                'after' => static::filterAuditAttributes(
                    $model,
                    $model->getAttributes()
                ),
            ]);
        });
    }

    protected static function recordAudit(
        Model $model,
        string $action,
        array $changes = []
    ): void {
        $companyId = static::resolveAuditCompanyId($model);

        if (! $companyId) {
            return;
        }

        AuditLog::create([
            'company_id' => $companyId,
            'user_id' => static::resolveAuditUserId($model, $action),
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'action' => $action,
            'changes' => $changes,
            'metadata' => static::resolveAuditMetadata(),
        ]);
    }

    protected static function resolveAuditUserId(Model $model, string $action): ?string
    {
        $userId = Auth::id();

        if ($userId) {
            return $userId;
        }

        if ($action === 'created') {
            return $model->getAttribute('created_by')
                ?? $model->getAttribute('updated_by');
        }

        return $model->getAttribute('updated_by')
            ?? $model->getAttribute('created_by');
    }

    protected static function resolveAuditCompanyId(Model $model): ?string
    {
        $companyId = $model->getAttribute('company_id');

        if ($companyId) {
            return $companyId;
        }

        $company = app(CompanyContext::class)->get();

        return $company?->id;
    }

    protected static function resolveAuditMetadata(): ?array
    {
        if (app()->runningInConsole()) {
            return null;
        }

        $request = request();

        if (! $request) {
            return null;
        }

        return [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }

    protected static function filterAuditAttributes(
        Model $model,
        array $attributes
    ): array {
        $ignored = array_flip($model->auditIgnoredAttributes());

        return array_diff_key($attributes, $ignored);
    }

    protected function auditIgnoredAttributes(): array
    {
        return [
            'company_id',
            'created_at',
            'updated_at',
            'deleted_at',
            'created_by',
            'updated_by',
        ];
    }
}
