<?php

namespace App\Services\Payroll;

class WpsFile
{
    public function __construct(
        public string $format,
        public string $extension,
        public string $content,
    ) {
    }
}
