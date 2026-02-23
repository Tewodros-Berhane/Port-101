<?php

namespace App\Http\Requests\Sales\Concerns;

trait ValidatesSalesLines
{
    protected function salesLineRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', 'uuid', $this->companyScopedExists('products')],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.9999'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
