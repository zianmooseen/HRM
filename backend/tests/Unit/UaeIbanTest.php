<?php

namespace Tests\Unit;

use App\Rules\UaeIban;
use PHPUnit\Framework\TestCase;

class UaeIbanTest extends TestCase
{
    public function test_it_normalizes_and_validates_uae_ibans(): void
    {
        $this->assertSame(
            'AE070331234567890123456',
            UaeIban::normalize('ae07 0331 2345 6789 0123 456'),
        );
        $this->assertTrue(UaeIban::isValid('AE070331234567890123456'));
        $this->assertFalse(UaeIban::isValid('AE070331234567890123457'));
        $this->assertFalse(UaeIban::isValid('GB82WEST12345698765432'));
    }
}
