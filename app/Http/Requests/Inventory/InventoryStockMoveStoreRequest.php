<?php

namespace App\Http\Requests\Inventory;

use App\Core\MasterData\Models\Product;
use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use App\Modules\Inventory\Models\InventoryStockMove;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryStockMoveStoreRequest extends FormRequest
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
            'related_sales_order_id' => ['nullable', 'uuid', $this->companyScopedExists('sales_orders')],
            'lines' => ['nullable', 'array'],
            'lines.*.source_lot_id' => ['nullable', 'uuid', $this->companyScopedExists('inventory_lots')],
            'lines.*.lot_code' => ['nullable', 'string', 'max:96'],
            'lines.*.quantity' => ['nullable', 'numeric', 'gt:0', 'max:999999999.9999'],
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

            $product = Product::query()
                ->where('company_id', $this->user()?->current_company_id)
                ->find($this->input('product_id'));

            if (! $product) {
                return;
            }

            if ($product->type !== Product::TYPE_STOCK) {
                $validator->errors()->add('product_id', 'Inventory moves only support stock products.');

                return;
            }

            $trackingMode = $product->tracking_mode ?? Product::TRACKING_NONE;
            $quantity = (float) $this->input('quantity', 0);
            $lines = collect($this->input('lines', []))
                ->filter(fn ($line) => is_array($line))
                ->values();

            if ($trackingMode === Product::TRACKING_NONE) {
                if ($lines->isNotEmpty()) {
                    $validator->errors()->add('lines', 'Untracked products should not include lot or serial lines.');
                }

                return;
            }

            if ($moveType === InventoryStockMove::TYPE_ADJUSTMENT) {
                $validator->errors()->add('move_type', 'Tracked adjustment moves are not supported yet.');

                return;
            }

            if ($moveType === InventoryStockMove::TYPE_RECEIPT && $lines->isEmpty()) {
                $validator->errors()->add('lines', 'Tracked receipt moves require lot or serial lines.');

                return;
            }

            if ($lines->isEmpty()) {
                return;
            }

            $lineQuantity = round((float) $lines->sum(fn ($line) => (float) ($line['quantity'] ?? 0)), 4);

            if (abs($lineQuantity - $quantity) > 0.0001) {
                $validator->errors()->add('lines', 'Lot or serial line quantities must match the move quantity.');
            }

            if ($trackingMode === Product::TRACKING_SERIAL) {
                if (abs($quantity - round($quantity)) > 0.0001) {
                    $validator->errors()->add('quantity', 'Serial-tracked products require whole-number quantities.');
                }

                if ($lines->count() !== (int) round($quantity)) {
                    $validator->errors()->add('lines', 'Serial-tracked moves require one line per serial.');
                }

                foreach ($lines as $index => $line) {
                    if ((float) ($line['quantity'] ?? 0) !== 1.0) {
                        $validator->errors()->add("lines.{$index}.quantity", 'Serial-tracked lines must have quantity 1.');
                    }
                }
            }

            if ($moveType === InventoryStockMove::TYPE_RECEIPT) {
                $codes = [];

                foreach ($lines as $index => $line) {
                    $code = trim((string) ($line['lot_code'] ?? ''));

                    if ($code === '') {
                        $validator->errors()->add("lines.{$index}.lot_code", 'Receipt lines require a lot or serial code.');
                    }

                    if ($trackingMode === Product::TRACKING_SERIAL && in_array($code, $codes, true)) {
                        $validator->errors()->add("lines.{$index}.lot_code", 'Serial codes must be unique.');
                    }

                    $codes[] = $code;
                }
            }

            if (in_array($moveType, [InventoryStockMove::TYPE_DELIVERY, InventoryStockMove::TYPE_TRANSFER], true)) {
                foreach ($lines as $index => $line) {
                    if (blank($line['source_lot_id'] ?? null)) {
                        $validator->errors()->add("lines.{$index}.source_lot_id", 'Source moves require an assigned lot or serial when lines are provided.');
                    }
                }
            }
        });
    }
}
