<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CoreRolesSeeder::class);

        $email = 'superadmin@port101.test';
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $user = User::factory()->create([
                'name' => 'Super Admin',
                'email' => $email,
            ]);
        }

        if (! $user->is_super_admin) {
            $user->forceFill([
                'is_super_admin' => true,
            ])->save();
        }
    }
}
