<?php

namespace App\Http\Requests\Core\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait CompanyMemberExistsRule
{
    protected function companyMemberExists(string $column = 'user_id'): Exists
    {
        $companyId = $this->user()?->current_company_id;

        return Rule::exists('company_users', $column)
            ->where('company_id', $companyId);
    }
}
