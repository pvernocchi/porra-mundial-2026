<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Crypto;
use PHPUnit\Framework\TestCase;

final class CryptoTest extends TestCase
{
    public function testEncryptDecryptRoundTrip(): void
    {
        $key = base64_decode(Crypto::generateKey(), true);
        $this->assertNotFalse($key);
        $c = new Crypto($key);
        $token = $c->encrypt('hello world');
        $this->assertSame('hello world', $c->decrypt($token));
    }

    public function testTamperedCiphertextFails(): void
    {
        $key = base64_decode(Crypto::generateKey(), true);
        $c = new Crypto($key);
        $token = $c->encrypt('secret');
        $tampered = strrev($token);
        $this->assertNull($c->decrypt($tampered));
    }

    public function testRejectsKeyOfWrongLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Crypto('short');
    }

    public function testHmacIsDeterministicAndComparable(): void
    {
        $key = base64_decode(Crypto::generateKey(), true);
        $c = new Crypto($key);
        $sig = $c->hmac('payload');
        $this->assertSame($sig, $c->hmac('payload'));
        $this->assertTrue($c->hmacEquals('payload', $sig));
        $this->assertFalse($c->hmacEquals('payload', 'tampered'));
    }
}
