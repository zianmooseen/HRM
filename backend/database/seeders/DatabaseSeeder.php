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

        // Feature flow step 3: seed UAE statutory leave types before HR users create requests.
        $this->call(LeaveTypeSeeder::class);

        // Feature flow step 4: seed maintained WPS provider reference data.
        $this->call(WpsProviderSeeder::class);

        // Feature flow step 5: create a runnable local login only when seed credentials are configured.
        $this->call(DevelopmentAdminSeeder::class);

        // Feature flow step 6: create deterministic demo records for end-to-end manual testing.
        $this->call(DemoDataSeeder::class);

        // Feature flow step 7: expand the demo tenant into a comprehensive dashboard scenario dataset.
        $this->call(DashboardScenarioSeeder::class);
    }
}
