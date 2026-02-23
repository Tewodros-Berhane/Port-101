<?php

namespace App\Http\Requests\Inventory;

use App\Modules\Inventory\Models\InventoryStockMove;
use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryStockMoveUpdateRequest extends FormRequest
{
    use CompanyScopedExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference' => ['nullable', 'string', 'max:64'],
            'move_type' => ['required', 'string', Rule::in(InventoryStockMove::TYPES)],
            'source_location_id' => ['nullable', 'uuid', $this->companyScopedExists('inventory_locations')],
            'destination_location_id' => ['nullable', 'uuid', $this->companyScopedExists('inventory_locations')],
            'product_id' => ['required', 'uuid', $this->companyScopedExists('products')],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.9999'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $moveType = $this->input('move_type');
            $source = $this->input('source_location_id');
            $destination = $this->input('destination_location_id');

            if (in_array($moveType, [InventoryStockMove::TYPE_DELIVERY, InventoryStockMove::TYPE_TRANSFER], true) && ! $source) {
                $validator->errors()->add('source_location_id', 'This move type requires a source location.');
            }

            if (in_array($moveType, [InventoryStockMove::TYPE_RECEIPT, InventoryStockMove::TYPE_DELIVERY, InventoryStockMove::TYPE_TRANSFER], true) && ! $destination) {
                $validator->errors()->add('destination_location_id', 'This move type requires a destination location.');
            }

            if ($moveType === InventoryStockMove::TYPE_TRANSFER && $source && $destination && $source === $destination) {
                $validator->errors()->add('destination_location_id', 'Transfer destination must differ from source.');
            }
        });
    }
}


