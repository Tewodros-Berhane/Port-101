<?php

namespace App\Core\Inventory;

use App\Core\Inventory\Models\InventoryLocation;
use App\Core\Inventory\Models\InventoryWarehouse;

class InventorySetupService
{
    public function ensureDefaults(string $companyId, ?string $actorId = null): void
    {
        $warehouse = InventoryWarehouse::query()
            ->where('company_id', $companyId)
            ->where('code', 'MAIN')
            ->first();

        if (! $warehouse) {
            $warehouse = InventoryWarehouse::create([
                'company_id' => $companyId,
                'code' => 'MAIN',
                'name' => 'Main Warehouse',
                'is_active' => true,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        }

        $defaults = [
            [
                'code' => 'STOCK',
                'name' => 'Stock',
                'type' => InventoryLocation::TYPE_INTERNAL,
                'warehouse_id' => $warehouse->id,
            ],
            [
                'code' => 'CUSTOMERS',
                'name' => 'Customers',
                'type' => InventoryLocation::TYPE_CUSTOMER,
                'warehouse_id' => null,
            ],
            [
                'code' => 'VENDORS',
                'name' => 'Vendors',
                'type' => InventoryLocation::TYPE_VENDOR,
                'warehouse_id' => null,
            ],
        ];

        foreach ($defaults as $location) {
            InventoryLocation::query()->firstOrCreate(
                [
                    'company_id' => $companyId,
                    'code' => $location['code'],
                ],
                [
                    'name' => $location['name'],
                    'type' => $location['type'],
                    'warehouse_id' => $location['warehouse_id'],
                    'is_active' => true,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ],
            );
        }
    }
}
