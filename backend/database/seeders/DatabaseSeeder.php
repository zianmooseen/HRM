<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Default seeding initializes the platform; HR defaults belong to client provisioning.
        $this->call([RoleAndPermissionSeeder::class, WpsProviderSeeder::class, PlatformAdminSeeder::class]);
    }
}
