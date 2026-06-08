<?php

namespace App\Services\Payroll;

use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;

interface WpsFileExporter
{
    public function provider(): string;

    public function generate(PayrollPeriod $period, Collection $rows): WpsFile;
}
