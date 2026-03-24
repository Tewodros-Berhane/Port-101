<?php

namespace App\Modules\Inventory;

use App\Core\MasterData\Models\Product;
use App\Modules\Inventory\Models\ProductBundle;
use App\Modules\Inventory\Models\ProductBundleComponent;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryBundleService
{
    /**
     * @param  array{
     *     enabled?: bool,
     *     mode?: string|null,
     *     components?: array<int, array{product_id?: string|null, quantity?: mixed}>
     * }|null  $payload
     */
    public function syncProductBundle(
        Product $product,
        ?array $payload,
        ?string $actorId = null
    ): ?ProductBundle {
        return DB::transaction(function () use ($product, $payload, $actorId) {
            $bundle = ProductBundle::withTrashed()
                ->with(['components' => fn ($query) => $query->withTrashed()])
                ->where('company_id', $product->company_id)
                ->where('product_id', $product->id)
                ->first();

            $enabled = (bool) data_get($payload, 'enabled', false);
            $components = collect(data_get($payload, 'components', []))
                ->map(function (array $component): array {
                    return [
                        'product_id' => $component['product_id'] ?? null,
                        'quantity' => round((float) ($component['quantity'] ?? 0), 4),
                    ];
                })
                ->filter(fn (array $component) => $component['product_id'] && $component['quantity'] > 0)
                ->values();

            if (! $enabled || $components->isEmpty()) {
                if ($bundle) {
                    $bundle->components->each(function (ProductBundleComponent $component) use ($actorId): void {
                        $component->update(['updated_by' => $actorId]);
                        $component->delete();
                    });

                    $bundle->update(['updated_by' => $actorId]);
                    $bundle->delete();
                }

                return null;
            }

            if (! $bundle) {
                $bundle = new ProductBundle([
                    'company_id' => $product->company_id,
                    'product_id' => $product->id,
                    'created_by' => $actorId,
                ]);
            } elseif ($bundle->trashed()) {
                $bundle->restore();
            }

            $bundle->fill([
                'mode' => (string) data_get($payload, 'mode', ProductBundle::MODE_SALES_ONLY),
                'is_active' => true,
                'updated_by' => $actorId,
            ]);
            $bundle->save();

            $existingComponents = ProductBundleComponent::withTrashed()
                ->where('bundle_id', $bundle->id)
                ->get()
                ->keyBy(fn (ProductBundleComponent $component) => (string) $component->component_product_id);

            $retainedComponentIds = [];

            $components->each(function (array $component, int $index) use (
                $bundle,
                $product,
                $existingComponents,
                &$retainedComponentIds,
                $actorId
            ): void {
                $componentProductId = (string) $component['product_id'];
                $retainedComponentIds[] = $componentProductId;

                $record = $existingComponents->get($componentProductId);

                if (! $record) {
                    ProductBundleComponent::create([
                        'company_id' => $product->company_id,
                        'bundle_id' => $bundle->id,
                        'component_product_id' => $componentProductId,
                        'sequence' => $index + 1,
                        'quantity' => $component['quantity'],
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);

                    return;
                }

                if ($record->trashed()) {
                    $record->restore();
                }

                $record->update([
                    'sequence' => $index + 1,
                    'quantity' => $component['quantity'],
                    'updated_by' => $actorId,
                ]);
            });

            $existingComponents
                ->filter(fn (ProductBundleComponent $component, string $productId) => ! in_array($productId, $retainedComponentIds, true))
                ->each(function (ProductBundleComponent $component) use ($actorId): void {
                    if ($component->trashed()) {
                        return;
                    }

                    $component->update(['updated_by' => $actorId]);
                    $component->delete();
                });

            return $bundle->fresh(['components.componentProduct']);
        });
    }

    /**
     * @return Collection<int, array{
     *     product_id: string,
     *     quantity: float,
     *     note: string
     * }>
     */
    public function explodeSalesOrder(SalesOrder $order): Collection
    {
        $order->loadMissing('lines.product.bundle.components.componentProduct');

        $demand = [];

        foreach ($order->lines as $line) {
            $product = $line->product;
            $lineQuantity = round((float) $line->quantity, 4);

            if (! $product || $lineQuantity <= 0) {
                continue;
            }

            $bundle = $product->bundle;

            if (
                $bundle
                && ! $bundle->trashed()
                && $bundle->is_active
                && $bundle->mode === ProductBundle::MODE_SALES_ONLY
            ) {
                $this->appendSalesBundleDemand($demand, $line, $product, $lineQuantity);

                continue;
            }

            if ($product->type !== Product::TYPE_STOCK) {
                continue;
            }

            $this->appendDemandRow(
                demand: $demand,
                productId: (string) $product->id,
                quantity: $lineQuantity,
                label: $product->name ?: $line->description ?: 'Sales line',
            );
        }

        return collect($demand)
            ->map(function (array $row): array {
                $labels = collect($row['labels'])->filter()->unique()->values()->all();

                return [
                    'product_id' => $row['product_id'],
                    'quantity' => round((float) $row['quantity'], 4),
                    'note' => $labels === []
                        ? 'Auto-reserved from confirmed sales order'
                        : 'Auto-reserved from confirmed sales order: '.implode(', ', $labels),
                ];
            })
            ->values();
    }

    /**
     * @param  array<string, array{product_id: string, quantity: float, labels: array<int, string>}>  $demand
     */
    private function appendSalesBundleDemand(
        array &$demand,
        SalesOrderLine $line,
        Product $product,
        float $lineQuantity
    ): void {
        $bundle = $product->bundle;

        if (! $bundle) {
            return;
        }

        foreach ($bundle->components as $component) {
            $componentProduct = $component->componentProduct;

            if (! $componentProduct || $componentProduct->type !== Product::TYPE_STOCK) {
                continue;
            }

            $this->appendDemandRow(
                demand: $demand,
                productId: (string) $componentProduct->id,
                quantity: round($lineQuantity * (float) $component->quantity, 4),
                label: sprintf(
                    '%s x %s',
                    $product->name ?: $line->description ?: 'Bundle',
                    rtrim(rtrim(number_format($lineQuantity, 4, '.', ''), '0'), '.'),
                ),
            );
        }
    }

    /**
     * @param  array<string, array{product_id: string, quantity: float, labels: array<int, string>}>  $demand
     */
    private function appendDemandRow(
        array &$demand,
        string $productId,
        float $quantity,
        string $label
    ): void {
        if (! isset($demand[$productId])) {
            $demand[$productId] = [
                'product_id' => $productId,
                'quantity' => 0,
                'labels' => [],
            ];
        }

        $demand[$productId]['quantity'] = round($demand[$productId]['quantity'] + $quantity, 4);
        $demand[$productId]['labels'][] = $label;
    }
}
