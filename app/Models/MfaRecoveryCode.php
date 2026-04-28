<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * One-shot recovery codes for MFA. Codes are stored as bcrypt-style
 * hashes (using password_hash) so a database leak does not reveal
 * usable codes.
 */
final class MfaRecoveryCode
{
    public const COUNT = 10;

    public function __construct(private Database $db)
    {
    }

    /**
     * Generates a fresh batch of recovery codes for a user, replacing
     * any existing ones.
     *
     * @return array<int, string> Plain codes (shown to user once).
     */
    public function regenerate(int $userId): array
    {
        $this->db->run('DELETE FROM {prefix:mfa_recovery_codes} WHERE user_id = :u', ['u' => $userId]);

        $codes = [];
        for ($i = 0; $i < self::COUNT; $i++) {
            $raw = self::randomCode();
            $codes[] = $raw;
            $this->db->insert('mfa_recovery_codes', [
                'user_id'    => $userId,
                'code_hash'  => password_hash(self::canonical($raw), PASSWORD_DEFAULT),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }
        return $codes;
    }

    public function consume(int $userId, string $code): bool
    {
        $needle = self::canonical($code);
        $rows = $this->db->fetchAll(
            'SELECT id, code_hash FROM {prefix:mfa_recovery_codes} WHERE user_id = :u AND used_at IS NULL',
            ['u' => $userId]
        );
        foreach ($rows as $r) {
            if (password_verify($needle, (string)$r['code_hash'])) {
                $this->db->update('mfa_recovery_codes', [
                    'used_at' => gmdate('Y-m-d H:i:s'),
                ], ['id' => (int)$r['id']]);
                return true;
            }
        }
        return false;
    }

    public function unusedCount(int $userId): int
    {
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS c FROM {prefix:mfa_recovery_codes} WHERE user_id = :u AND used_at IS NULL',
            ['u' => $userId]
        );
        return (int)($row['c'] ?? 0);
    }

    private static function randomCode(): string
    {
        // 10 char base32 -> 50 bits, formatted as XXXXX-XXXXX for readability.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no I/O/0/1
        $out = '';
        for ($i = 0; $i < 10; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return substr($out, 0, 5) . '-' . substr($out, 5, 5);
    }

    private static function canonical(string $code): string
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
        return $code;
    }
}
