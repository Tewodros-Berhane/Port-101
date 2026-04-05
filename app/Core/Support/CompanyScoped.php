<?php

namespace App\Core\Support;

use Illuminate\Database\Eloquent\Builder;

trait CompanyScoped
{
    protected static function bootCompanyScoped(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $company = app(CompanyContext::class)->get();

            if ($company) {
                $builder->where($builder->getModel()->getTable().'.company_id', $company->id);
                return;
            }

            $user = auth()->user();

            if (
                $user
                && ! $user->is_super_admin
                && $user->current_company_id
            ) {
                $builder->where(
                    $builder->getModel()->getTable().'.company_id',
                    $user->current_company_id,
                );
            }
        });

        static::creating(function ($model) {
            if (! $model->company_id) {
                $company = app(CompanyContext::class)->get();

                if ($company) {
                    $model->company_id = $company->id;
                }
            }
        });
    }
}
