<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Totp;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    public function testBase32RoundTrip(): void
    {
        $bin = random_bytes(20);
        $b32 = Totp::base32Encode($bin);
        $this->assertSame($bin, Totp::base32Decode($b32));
    }

    public function testGenerateAndVerify(): void
    {
        $secret = Totp::generateSecret();
        $now = time();
        $key = Totp::base32Decode($secret);
        $code = Totp::compute($key, (int)floor($now / Totp::PERIOD));
        $this->assertTrue(Totp::verify($secret, $code, 1, $now));
    }

    public function testRejectsWrongCode(): void
    {
        $secret = Totp::generateSecret();
        $this->assertFalse(Totp::verify($secret, '000000'));
        $this->assertFalse(Totp::verify($secret, ''));
        $this->assertFalse(Totp::verify($secret, 'abcdef'));
        $this->assertFalse(Totp::verify($secret, '12345')); // wrong length
    }

    public function testToleranceWindow(): void
    {
        $secret = Totp::generateSecret();
        $now = time();
        $key = Totp::base32Decode($secret);
        // Code from one step ago should still verify with default tolerance.
        $oldCode = Totp::compute($key, (int)floor($now / Totp::PERIOD) - 1);
        $this->assertTrue(Totp::verify($secret, $oldCode, 1, $now));

        // Code from far in the past should not verify.
        $stale = Totp::compute($key, (int)floor($now / Totp::PERIOD) - 5);
        $this->assertFalse(Totp::verify($secret, $stale, 1, $now));
    }

    public function testProvisioningUriContainsRequiredParams(): void
    {
        $uri = Totp::provisioningUri('JBSWY3DPEHPK3PXP', 'me@example.com', 'Acme');
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContainsString('issuer=Acme', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
    }
}
