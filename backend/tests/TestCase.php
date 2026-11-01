<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    // Older HR tests exercise tenant services in a single disposable database.
    // Tenancy integration tests override this to test the actual platform-only schema.
    protected bool $legacySchema = true;

    private static ?bool $previousLegacySchema = null;

    protected function setUp(): void
    {
        if (self::$previousLegacySchema !== $this->legacySchema) {
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$inMemoryConnections = [];
            self::$previousLegacySchema = $this->legacySchema;
        }
        parent::setUp();
    }
}
