<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Password policy and hashing.
 *
 * Policy (mirrors README):
 *  - Minimum 8 characters.
 *  - Must contain characters from at least 3 of these classes:
 *    lowercase letter, uppercase letter, digit, symbol.
 *  - Must not appear in the bundled common-passwords list.
 */
final class Password
{
    public const MIN_LENGTH = 8;

    public static function hash(string $plain): string
    {
        // PASSWORD_DEFAULT is bcrypt today; PHP will pick argon2id if/when
        // it becomes the default. Either is acceptable.
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new \RuntimeException('password_hash() failed');
        }
        return $hash;
    }

    public static function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }

    /**
     * @return array<int, string> List of validation error messages.
     */
    public static function validate(string $password, ?string $commonPasswordsFile = null): array
    {
        $errors = [];

        if (mb_strlen($password) < self::MIN_LENGTH) {
            $errors[] = sprintf('La contraseña debe tener al menos %d caracteres.', self::MIN_LENGTH);
        }

        $classes = 0;
        if (preg_match('/[a-z]/u', $password) === 1) { $classes++; }
        if (preg_match('/[A-Z]/u', $password) === 1) { $classes++; }
        if (preg_match('/\d/', $password)        === 1) { $classes++; }
        if (preg_match('/[^A-Za-z0-9]/u', $password) === 1) { $classes++; }
        if ($classes < 3) {
            $errors[] = 'La contraseña debe combinar al menos 3 de: minúsculas, mayúsculas, dígitos y símbolos.';
        }

        if ($commonPasswordsFile !== null && self::isCommon($password, $commonPasswordsFile)) {
            $errors[] = 'La contraseña aparece en una lista de contraseñas comunes; elige otra.';
        }

        return $errors;
    }

    private static function isCommon(string $password, string $file): bool
    {
        if (!is_file($file)) {
            return false;
        }
        $needle = strtolower($password);
        $fh = @fopen($file, 'r');
        if ($fh === false) {
            return false;
        }
        try {
            while (($line = fgets($fh)) !== false) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                if (strtolower($line) === $needle) {
                    return true;
                }
            }
        } finally {
            fclose($fh);
        }
        return false;
    }
}
