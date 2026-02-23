<?php

namespace App\Modules\Sales;

class SalesDocumentTotalsService
{
    /**
     * @param  array<int, array<string, mixed>>  $rawLines
     * @return array{lines: array<int, array<string, mixed>>, totals: array{subtotal: float, discount_total: float, tax_total: float, grand_total: float}}
     */
    public function calculate(array $rawLines): array
    {
        $lines = [];
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $grandTotal = 0.0;

        foreach ($rawLines as $line) {
            $quantity = round((float) ($line['quantity'] ?? 0), 4);
            $unitPrice = round((float) ($line['unit_price'] ?? 0), 2);
            $discountPercent = round((float) ($line['discount_percent'] ?? 0), 2);
            $taxRate = round((float) ($line['tax_rate'] ?? 0), 2);

            $lineGross = round($quantity * $unitPrice, 2);
            $lineDiscount = round($lineGross * ($discountPercent / 100), 2);
            $lineSubtotal = round($lineGross - $lineDiscount, 2);
            $lineTax = round($lineSubtotal * ($taxRate / 100), 2);
            $lineTotal = round($lineSubtotal + $lineTax, 2);

            $subtotal += $lineGross;
            $discountTotal += $lineDiscount;
            $taxTotal += $lineTax;
            $grandTotal += $lineTotal;

            $lines[] = [
                'product_id' => $line['product_id'] ?: null,
                'description' => (string) ($line['description'] ?? ''),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'tax_rate' => $taxRate,
                'line_subtotal' => $lineSubtotal,
                'line_total' => $lineTotal,
            ];
        }

        return [
            'lines' => $lines,
            'totals' => [
                'subtotal' => round($subtotal, 2),
                'discount_total' => round($discountTotal, 2),
                'tax_total' => round($taxTotal, 2),
                'grand_total' => round($grandTotal, 2),
            ],
        ];
    }
}


