<?php

namespace App\Http\Requests\Hr;

use Illuminate\Validation\Rule;

class HrReimbursementClaimUpdateRequest extends HrReimbursementClaimStoreRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['action'] = ['required', Rule::in(['save', 'submit'])];

        return $rules;
    }
}
