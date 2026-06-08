<?php

namespace App\Services\Payroll;

class EmiratesNbdSifExporter extends GenericSifExporter
{
    public function provider(): string
    {
        return 'emirates_nbd';
    }
}
