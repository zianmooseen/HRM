<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        if ($this->legacySchema) {
            $app['config']->set('tenancy.legacy_test_schema', true);
            $app->make('migrator')->path(database_path('migrations/client'));
        }

        return $app;
    }
}
