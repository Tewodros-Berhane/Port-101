<?php

namespace App\Http\Requests\Core\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait CompanyScopedExistsRule
{
    protected function companyScopedExists(
        string $table,
        string $column = 'id'
    ): Exists {
        $companyId = $this->user()?->current_company_id;

        return Rule::exists($table, $column)
            ->where('company_id', $companyId);
    }
}
