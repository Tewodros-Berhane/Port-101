<?php

namespace App\Modules\Purchasing;

class PurchasingTotalsService
{
    /**
     * @param  array<int, array<string, mixed>>  $rawLines
     * @return array{lines: array<int, array<string, mixed>>, totals: array{subtotal: float, tax_total: float, grand_total: float}}
     */
    public function calculate(array $rawLines): array
    {
        $lines = [];
        $subtotal = 0.0;
        $taxTotal = 0.0;
        $grandTotal = 0.0;

        foreach ($rawLines as $line) {
            $quantity = round((float) ($line['quantity'] ?? 0), 4);
            $unitCost = round((float) ($line['unit_cost'] ?? 0), 2);
            $taxRate = round((float) ($line['tax_rate'] ?? 0), 2);

            $lineSubtotal = round($quantity * $unitCost, 2);
            $lineTax = round($lineSubtotal * ($taxRate / 100), 2);
            $lineTotal = round($lineSubtotal + $lineTax, 2);

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;
            $grandTotal += $lineTotal;

            $lines[] = [
                'product_id' => $line['product_id'] ?: null,
                'description' => (string) ($line['description'] ?? ''),
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'tax_rate' => $taxRate,
                'line_subtotal' => $lineSubtotal,
                'line_total' => $lineTotal,
            ];
        }

        return [
            'lines' => $lines,
            'totals' => [
                'subtotal' => round($subtotal, 2),
                'tax_total' => round($taxTotal, 2),
                'grand_total' => round($grandTotal, 2),
            ],
        ];
    }
}
