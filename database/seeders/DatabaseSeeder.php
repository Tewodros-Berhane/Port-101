<?php

namespace Database\Seeders;

use App\Core\Company\Models\Company;
use App\Core\RBAC\Models\Role;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CoreRolesSeeder::class);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $companyName = 'Test Company';
        $company = Company::create([
            'name' => $companyName,
            'slug' => Str::slug($companyName),
            'timezone' => config('app.timezone', 'UTC'),
            'owner_id' => $user->id,
        ]);

        $ownerRole = Role::query()
            ->whereNull('company_id')
            ->where('slug', 'owner')
            ->first();

        $company->users()->attach($user->id, [
            'role_id' => $ownerRole?->id,
            'is_owner' => true,
        ]);

        $user->forceFill([
            'current_company_id' => $company->id,
        ])->save();
    }
}
