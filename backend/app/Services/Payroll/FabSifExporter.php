<?php

namespace App\Services\Payroll;

class FabSifExporter extends GenericSifExporter
{
    public function provider(): string
    {
        return 'fab';
    }
}
