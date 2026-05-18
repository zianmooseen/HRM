<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Feature flow step 1: seed statutory rules before company policies reference them.
        $this->call(LegalRuleSeeder::class);

        // Feature flow step 2: seed roles and permissions before users receive scoped access.
        $this->call(RoleAndPermissionSeeder::class);

        // Feature flow step 3: create a runnable local login only when seed credentials are configured.
        $this->call(DevelopmentAdminSeeder::class);
    }
}
