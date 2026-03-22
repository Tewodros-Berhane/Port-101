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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $order = $this->route('order');

            if (! $order || ! $this->filled('lines')) {
                return;
            }

            $lineIds = collect($this->input('lines', []))
                ->pluck('line_id')
                ->filter()
                ->values();

            if ($lineIds->isEmpty()) {
                return;
            }

            $allowedLineIds = $order->lines()
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();

            foreach ($lineIds as $index => $lineId) {
                if (! in_array((string) $lineId, $allowedLineIds, true)) {
                    $validator->errors()->add(
                        "lines.{$index}.line_id",
                        'Selected line does not belong to this purchase order.',
                    );
                }
            }
        });
    }
}
