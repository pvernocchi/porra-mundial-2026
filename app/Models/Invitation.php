<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Invitations: a user can only be created via an invitation link sent
 * by an administrator. Tokens are stored as SHA-256 hashes; the raw
 * value lives only in the URL emailed to the recipient.
 */
final class Invitation
{
    public const VALIDITY_HOURS = 48;
    public const TOKEN_BYTES = 32;

    public function __construct(private Database $db)
    {
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return array{id:int, token:string} raw token returned only here
     */
    public function create(string $fullName, string $email, string $role, int $createdBy): array
    {
        // Revoke any pending invitations for the same email.
        $this->db->run(
            'UPDATE {prefix:invitations} SET revoked_at = :now WHERE email = :e AND used_at IS NULL AND revoked_at IS NULL',
            ['e' => strtolower($email), 'now' => gmdate('Y-m-d H:i:s')]
        );

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $now   = gmdate('Y-m-d H:i:s');
        $exp   = gmdate('Y-m-d H:i:s', time() + self::VALIDITY_HOURS * 3600);

        $id = (int)$this->db->insert('invitations', [
            'email'      => strtolower($email),
            'full_name'  => $fullName,
            'role'       => $role === 'admin' ? 'admin' : 'user',
            'token_hash' => self::hashToken($token),
            'created_by' => $createdBy,
            'created_at' => $now,
            'expires_at' => $exp,
        ]);
        return ['id' => $id, 'token' => $token];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findValidByToken(string $token): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM {prefix:invitations} WHERE token_hash = :h LIMIT 1',
            ['h' => self::hashToken($token)]
        );
        if ($row === null) {
            return null;
        }
        if (!empty($row['used_at']) || !empty($row['revoked_at'])) {
            return null;
        }
        $exp = strtotime((string)$row['expires_at']);
        if ($exp === false || $exp < time()) {
            return null;
        }
        return $row;
    }

    public function markUsed(int $id): void
    {
        $this->db->update('invitations', ['used_at' => gmdate('Y-m-d H:i:s')], ['id' => $id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPending(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM {prefix:invitations} WHERE used_at IS NULL AND revoked_at IS NULL ORDER BY id DESC'
        );
    }

    public function status(array $row): string
    {
        if (!empty($row['used_at'])) {
            return 'used';
        }
        if (!empty($row['revoked_at'])) {
            return 'revoked';
        }
        $exp = strtotime((string)$row['expires_at']);
        if ($exp !== false && $exp < time()) {
            return 'expired';
        }
        return 'pending';
    }
}
