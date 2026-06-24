<?php

namespace App\Services\Payroll;

use App\Models\Company;
use App\Models\WpsProvider;

class WpsFileExporterFactory
{
    public function make(Company $company, ?WpsProvider $provider = null): WpsFileExporter
    {
        return match ($provider?->export_profile ?? $company->wps_provider ?? 'generic') {
            'fab' => new FabSifExporter(),
            'emirates_nbd' => new EmiratesNbdSifExporter(),
            default => new GenericSifExporter(),
        };
    }
}
