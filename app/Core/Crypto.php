<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Symmetric encryption / signing helpers built on libsodium.
 *
 * `encrypt`/`decrypt` produce/consume url-safe base64 strings prefixed
 * with the 24-byte nonce. They use crypto_secretbox (XSalsa20-Poly1305).
 */
final class Crypto
{
    public function __construct(private string $key)
    {
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \InvalidArgumentException(
                'APP_KEY must decode to ' . SODIUM_CRYPTO_SECRETBOX_KEYBYTES . ' bytes.'
            );
        }
    }

    public static function generateKey(): string
    {
        return base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }

    public function encrypt(string $plaintext): string
    {
        $nonce  = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $this->key);
        return rtrim(strtr(base64_encode($nonce . $cipher), '+/', '-_'), '=');
    }

    public function decrypt(string $token): ?string
    {
        $bin = base64_decode(strtr($token, '-_', '+/'), true);
        if ($bin === false || strlen($bin) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + 1) {
            return null;
        }
        $nonce  = substr($bin, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($bin, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain  = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);
        return $plain === false ? null : $plain;
    }

    public function hmac(string $data): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $data, $this->key, true)), '+/', '-_'), '=');
    }

    public function hmacEquals(string $data, string $signature): bool
    {
        return hash_equals($this->hmac($data), $signature);
    }
}
