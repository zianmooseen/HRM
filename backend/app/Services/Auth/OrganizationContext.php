<?php

namespace App\Services\Auth;

class OrganizationContext
{
    public function __construct(
        public readonly int $companyId,
        public readonly ?int $branchId = null,
    ) {}

    public static function current(): ?self
    {
        return app()->bound('request') ? request()->attributes->get(self::class) : null;
    }
}
