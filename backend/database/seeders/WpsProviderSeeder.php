<?php

namespace Database\Seeders;

use App\Models\WpsProvider;
use Illuminate\Database\Seeder;

class WpsProviderSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Generic WPS Agent', 'code' => 'generic', 'provider_type' => 'other', 'export_profile' => 'generic'],
            ['name' => 'First Abu Dhabi Bank', 'code' => 'fab', 'provider_type' => 'bank', 'export_profile' => 'fab'],
            ['name' => 'Emirates NBD', 'code' => 'emirates_nbd', 'provider_type' => 'bank', 'export_profile' => 'emirates_nbd'],
        ] as $provider) {
            WpsProvider::query()->updateOrCreate(
                ['code' => $provider['code']],
                [...$provider, 'integration_type' => 'file_export', 'status' => 'active'],
            );
        }
    }
}
