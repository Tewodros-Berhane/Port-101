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
                'name' => 'Manage Master Data',
                'slug' => 'core.master_data.manage',
                'group' => 'core',
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
