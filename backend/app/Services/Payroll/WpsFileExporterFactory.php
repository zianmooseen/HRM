<?php

namespace App\Services\Payroll;

use App\Models\Company;

class WpsFileExporterFactory
{
    public function make(Company $company): WpsFileExporter
    {
        return match ($company->wps_provider ?? 'generic') {
            'fab' => new FabSifExporter(),
            'emirates_nbd' => new EmiratesNbdSifExporter(),
            default => new GenericSifExporter(),
        };
    }
}
