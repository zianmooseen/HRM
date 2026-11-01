<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class PreflightCache extends Command
{
    protected $signature = 'cache:preflight';

    protected $description = 'Verify deployment can write cache values and acquire provisioning locks before changing schemas';

    public function handle(): int
    {
        $key = 'deployment-preflight:'.Str::uuid();
        $cache = Cache::store();
        $lock = null;
        try {
            if (! $cache->put($key, $key, 30) || $cache->get($key) !== $key) {
                throw new RuntimeException('The configured cache cannot write and read a value.');
            }
            $lock = $cache->lock($key.':lock', 30);
            if (! $lock->get()) {
                throw new RuntimeException('The configured cache cannot acquire a provisioning lock.');
            }
        } catch (\Throwable $exception) {
            $this->error('Cache preflight failed: '.$exception->getMessage());
            $this->warn('No schema changes were made. The deploy user and PHP-FPM user must both be able to write the configured cache and lock directories.');

            return self::FAILURE;
        } finally {
            // Do not hide the original failure if cleanup also fails on an unwritable store.
            try {
                $lock?->release();
                $cache->forget($key);
            } catch (\Throwable) {
                // Probe entries expire automatically after 30 seconds.
            }
        }
        $this->info('Cache writes and provisioning locks verified.');

        return self::SUCCESS;
    }
}
