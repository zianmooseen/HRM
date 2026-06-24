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

    public function test_it_explains_format_and_checksum_errors(): void
    {
        $rule = new UaeIban;
        $formatErrors = [];
        $checksumErrors = [];

        $rule->validate('bank_iban', 'AE123', function (string $message) use (&$formatErrors): void {
            $formatErrors[] = $message;
        });
        $rule->validate('bank_iban', 'AE120420000000001234567', function (string $message) use (&$checksumErrors): void {
            $checksumErrors[] = $message;
        });

        $this->assertSame(
            ['Enter a 23-character UAE IBAN starting with AE followed by 21 digits.'],
            $formatErrors,
        );
        $this->assertSame(
            ['The UAE IBAN checksum is invalid. Copy the exact IBAN from the employee\'s bank statement or banking app.'],
            $checksumErrors,
        );
    }
}
