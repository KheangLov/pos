<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Search indexing runs separately via scout:import after seeding
        config(['scout.driver' => 'null']);

        Model::unguard();

        $this->call([
            RolePermissionSeeder::class,
            BrewHavenSeeder::class,
            TechHubSeeder::class,
        ]);

        // Super admin with access to everything (company 1 / branch 1 as home base)
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@pos.test',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'company_id' => 1,
            'branch_id' => 1,
        ]);
        $admin->assignRole('Admin');

        $this->call([
            SalesHistorySeeder::class,
        ]);

        Model::reguard();
    }
}
