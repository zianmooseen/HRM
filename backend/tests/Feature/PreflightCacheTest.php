<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PreflightCacheTest extends TestCase
{
    protected bool $legacySchema = false;

    public function test_real_file_cache_creates_nested_paths_and_can_lock_without_leaving_probe_values(): void
    {
        $directory = sys_get_temp_dir().'/hrm-cache-preflight-'.bin2hex(random_bytes(8));
        config([
            'cache.default' => 'preflight_test',
            'cache.stores.preflight_test' => ['driver' => 'file', 'path' => $directory.'/data', 'lock_path' => $directory.'/locks'],
        ]);
        try {
            $this->artisan('cache:preflight')->expectsOutputToContain('Cache writes and provisioning locks verified')->assertSuccessful();
            $this->assertDirectoryExists($directory.'/data');
            $this->assertDirectoryExists($directory.'/locks');
            $this->assertCount(0, File::allFiles($directory));
        } finally {
            Cache::purge('preflight_test');
            File::deleteDirectory($directory);
        }
    }

    public function test_unusable_lock_directory_stops_preflight_with_an_actionable_error(): void
    {
        $directory = sys_get_temp_dir().'/hrm-cache-preflight-'.bin2hex(random_bytes(8));
        File::makeDirectory($directory);
        File::put($directory.'/blocked', 'A file cannot contain cache lock directories.');
        config([
            'cache.default' => 'preflight_test',
            'cache.stores.preflight_test' => ['driver' => 'file', 'path' => $directory.'/data', 'lock_path' => $directory.'/blocked/locks'],
        ]);
        try {
            $this->artisan('cache:preflight')->expectsOutputToContain('Cache preflight failed')->expectsOutputToContain('No schema changes were made')->assertFailed();
        } finally {
            Cache::purge('preflight_test');
            File::deleteDirectory($directory);
        }
    }
}
