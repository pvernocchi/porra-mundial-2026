<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Password;
use PHPUnit\Framework\TestCase;

final class PasswordTest extends TestCase
{
    public function testRejectsShortPasswords(): void
    {
        $errors = Password::validate('Aa1!aaa');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('al menos 8', $errors[0]);
    }

    public function testRequiresThreeCharClasses(): void
    {
        // Only lowercase + digits = 2 classes -> should fail
        $errors = Password::validate('aaaaaa11');
        $this->assertNotEmpty($errors);
    }

    public function testAcceptsStrongPassword(): void
    {
        $errors = Password::validate('Sup3rSecreto!');
        $this->assertSame([], $errors);
    }

    public function testRejectsCommonPassword(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pwds');
        file_put_contents($tmp, "supersecret\nSup3rSecreto!\n");
        try {
            $errors = Password::validate('Sup3rSecreto!', $tmp);
            $this->assertNotEmpty($errors);
            $this->assertStringContainsString('comunes', $errors[0]);
        } finally {
            @unlink($tmp);
        }
    }

    public function testHashAndVerifyRoundTrip(): void
    {
        $hash = Password::hash('Sup3rSecreto!');
        $this->assertTrue(Password::verify('Sup3rSecreto!', $hash));
        $this->assertFalse(Password::verify('otra cosa', $hash));
    }
}
