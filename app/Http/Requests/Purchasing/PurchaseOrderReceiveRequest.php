<?php

namespace App\Http\Requests\Purchasing;

use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderReceiveRequest extends FormRequest
{
    use CompanyScopedExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lines' => ['nullable', 'array'],
            'lines.*.line_id' => ['required_with:lines', 'uuid', $this->companyScopedExists('purchase_order_lines')],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'gt:0', 'max:999999999.9999'],
        ];
    }
}
