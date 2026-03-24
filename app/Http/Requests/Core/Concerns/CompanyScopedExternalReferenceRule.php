<?php

namespace App\Http\Requests\Core\Concerns;

use Illuminate\Validation\Rule;

trait CompanyScopedExternalReferenceRule
{
    /**
     * @return array<int, mixed>
     */
    protected function externalReferenceRules(string $table, ?string $ignoreId = null): array
    {
        $rule = Rule::unique($table, 'external_reference')
            ->where('company_id', $this->user()?->current_company_id);

        if ($ignoreId) {
            $rule->ignore($ignoreId);
        }

        return [
            'nullable',
            'string',
            'max:191',
            $rule,
        ];
    }
}
