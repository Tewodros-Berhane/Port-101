<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use App\Http\Requests\Core\Concerns\CompanyScopedExternalReferenceRule;
use App\Http\Requests\Sales\Concerns\ValidatesSalesLines;
use Illuminate\Foundation\Http\FormRequest;

class SalesOrderUpdateRequest extends FormRequest
{
    use CompanyScopedExistsRule;
    use CompanyScopedExternalReferenceRule;
    use ValidatesSalesLines;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $orderId = (string) $this->route('order')?->id;

        return [
            'external_reference' => $this->externalReferenceRules('sales_orders', $orderId),
            'partner_id' => ['required', 'uuid', $this->companyScopedExists('partners')],
            'order_date' => ['required', 'date'],
            ...$this->salesLineRules(),
        ];
    }
}
