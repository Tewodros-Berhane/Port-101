<?php

namespace Database\Seeders;

use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use Illuminate\Database\Seeder;

class CoreRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'View Company',
                'slug' => 'core.company.view',
                'group' => 'core',
            ],
            [
                'name' => 'Manage Company',
                'slug' => 'core.company.manage',
                'group' => 'core',
            ],
            [
                'name' => 'View Roles',
                'slug' => 'core.roles.view',
                'group' => 'core',
            ],
            [
                'name' => 'Manage Roles',
                'slug' => 'core.roles.manage',
                'group' => 'core',
            ],
            [
                'name' => 'View Permissions',
                'slug' => 'core.permissions.view',
                'group' => 'core',
            ],
            [
                'name' => 'Manage Permissions',
                'slug' => 'core.permissions.manage',
                'group' => 'core',
            ],
            [
                'name' => 'Manage Users',
                'slug' => 'core.users.manage',
                'group' => 'core',
            ],
            [
                'name' => 'Manage Settings',
                'slug' => 'core.settings.manage',
                'group' => 'core',
            ],
            [
                'name' => 'View Audit Logs',
                'slug' => 'core.audit_logs.view',
                'group' => 'core',
            ],
            [
                'name' => 'Manage Audit Logs',
                'slug' => 'core.audit_logs.manage',
                'group' => 'core',
            ],
            [
                'name' => 'View Partners',
                'slug' => 'core.partners.view',
                'group' => 'master_data',
            ],
            [
                'name' => 'Manage Partners',
                'slug' => 'core.partners.manage',
                'group' => 'master_data',
            ],
            [
                'name' => 'View Contacts',
                'slug' => 'core.contacts.view',
                'group' => 'master_data',
            ],
            [
                'name' => 'Manage Contacts',
                'slug' => 'core.contacts.manage',
                'group' => 'master_data',
            ],
            [
                'name' => 'View Addresses',
                'slug' => 'core.addresses.view',
                'group' => 'master_data',
            ],
            [
                'name' => 'Manage Addresses',
                'slug' => 'core.addresses.manage',
                'group' => 'master_data',
            ],
            [
                'name' => 'View Products',
                'slug' => 'core.products.view',
                'group' => 'master_data',
            ],
            [
                'name' => 'Manage Products',
                'slug' => 'core.products.manage',
                'group' => 'master_data',
            ],
            [
                'name' => 'View Taxes',
                'slug' => 'core.taxes.view',
                'group' => 'master_data',
            ],
            [
                'name' => 'Manage Taxes',
                'slug' => 'core.taxes.manage',
                'group' => 'master_data',
            ],
            [
                'name' => 'View Currencies',
                'slug' => 'core.currencies.view',
                'group' => 'master_data',
            ],
            [
                'name' => 'Manage Currencies',
                'slug' => 'core.currencies.manage',
                'group' => 'master_data',
            ],
            [
                'name' => 'View Units of Measure',
                'slug' => 'core.uoms.view',
                'group' => 'master_data',
            ],
            [
                'name' => 'Manage Units of Measure',
                'slug' => 'core.uoms.manage',
                'group' => 'master_data',
            ],
            [
                'name' => 'View Price Lists',
                'slug' => 'core.price_lists.view',
                'group' => 'master_data',
            ],
            [
                'name' => 'Manage Price Lists',
                'slug' => 'core.price_lists.manage',
                'group' => 'master_data',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'group' => $permission['group'],
                ]
            );
        }

        $ownerRole = Role::firstOrCreate(
            ['slug' => 'owner', 'company_id' => null],
            ['name' => 'Owner', 'description' => 'Full access', 'company_id' => null]
        );

        $memberRole = Role::firstOrCreate(
            ['slug' => 'member', 'company_id' => null],
            ['name' => 'Member', 'description' => 'Limited access', 'company_id' => null]
        );

        $allPermissionIds = Permission::query()->pluck('id')->all();
        $ownerRole->permissions()->sync($allPermissionIds);

        $memberPermissionIds = Permission::query()
            ->whereIn('slug', ['core.company.view'])
            ->pluck('id')
            ->all();

        $memberRole->permissions()->sync($memberPermissionIds);
    }
}
