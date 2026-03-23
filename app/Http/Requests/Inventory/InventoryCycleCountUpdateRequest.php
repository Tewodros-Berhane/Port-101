<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use Illuminate\Foundation\Http\FormRequest;

class InventoryCycleCountUpdateRequest extends FormRequest
{
    use CompanyScopedExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array'],
            'lines.*.id' => ['required', 'uuid', $this->companyScopedExists('inventory_cycle_count_lines')],
            'lines.*.counted_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999.9999'],
        ];
    }
}
