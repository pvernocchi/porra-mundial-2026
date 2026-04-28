<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Self-contained RFC 6238 TOTP implementation (HMAC-SHA1, 6 digits, 30s
 * step). No external dependencies — works on any PHP 8.1 host.
 *
 * Compatible with Google Authenticator, Authy, 1Password, FreeOTP, etc.
 */
final class Totp
{
    public const PERIOD = 30;
    public const DIGITS = 6;
    public const ALGORITHM = 'sha1';

    /** Generates a fresh base32-encoded shared secret. */
    public static function generateSecret(int $length = 20): string
    {
        return self::base32Encode(random_bytes($length));
    }

    /**
     * Returns the otpauth:// provisioning URI used by authenticator apps.
     */
    public static function provisioningUri(string $secret, string $accountName, string $issuer): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($accountName);
        $params = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => strtoupper(self::ALGORITHM),
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ]);
        return 'otpauth://totp/' . $label . '?' . $params;
    }

    /**
     * Verify a 6-digit code against the secret.
     *
     * Accepts a `tolerance` window (each step is 30 seconds), default ±1.
     */
    public static function verify(string $secret, string $code, int $tolerance = 1, ?int $now = null): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if ($code === '' || !ctype_digit($code) || strlen($code) !== self::DIGITS) {
            return false;
        }
        $key = self::base32Decode($secret);
        if ($key === '') {
            return false;
        }
        $now = $now ?? time();
        $counter = (int)floor($now / self::PERIOD);
        for ($i = -$tolerance; $i <= $tolerance; $i++) {
            if (hash_equals(self::compute($key, $counter + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Compute the code at a given counter.
     */
    public static function compute(string $key, int $counter): string
    {
        $bin = pack('N*', 0) . pack('N*', $counter); // 8-byte big-endian counter
        $hash = hash_hmac(self::ALGORITHM, $bin, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $part = substr($hash, $offset, 4);
        $value = (
            ((ord($part[0]) & 0x7F) << 24) |
            ((ord($part[1]) & 0xFF) << 16) |
            ((ord($part[2]) & 0xFF) << 8)  |
            ( ord($part[3]) & 0xFF)
        );
        $mod = 10 ** self::DIGITS;
        return str_pad((string)($value % $mod), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function base32Encode(string $bin): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = '';
        $bits = '';
        foreach (str_split($bin) as $ch) {
            $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
        }
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $out  .= $alphabet[bindec($chunk)];
        }
        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32) ?? '');
        if ($b32 === '') {
            return '';
        }
        $bits = '';
        foreach (str_split($b32) as $ch) {
            $idx = strpos($alphabet, $ch);
            if ($idx === false) {
                continue;
            }
            $bits .= str_pad(decbin($idx), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }
        return $out;
    }
}
