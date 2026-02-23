<?php

namespace App\Modules\Accounting;

class AccountingTotalsService
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
            $unitPrice = round((float) ($line['unit_price'] ?? 0), 2);
            $taxRate = round((float) ($line['tax_rate'] ?? 0), 2);

            $lineSubtotal = round($quantity * $unitPrice, 2);
            $lineTax = round($lineSubtotal * ($taxRate / 100), 2);
            $lineTotal = round($lineSubtotal + $lineTax, 2);

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;
            $grandTotal += $lineTotal;

            $lines[] = [
                'product_id' => $line['product_id'] ?: null,
                'description' => (string) ($line['description'] ?? ''),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
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
