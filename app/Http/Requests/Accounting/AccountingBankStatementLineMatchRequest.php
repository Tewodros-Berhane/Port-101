<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use Illuminate\Foundation\Http\FormRequest;

class AccountingBankStatementLineMatchRequest extends FormRequest
{
    use CompanyScopedExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_id' => ['nullable', 'uuid', $this->companyScopedExists('accounting_payments')],
        ];
    }
}
