<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DevelopmentAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Backward-compatible name; development bootstrap now creates only a platform admin.
        $this->call(PlatformAdminSeeder::class);
    }
}
