<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * MFA credential row. A single user can have multiple credentials,
 * mixing TOTP and WebAuthn.
 *
 * type:
 *   - totp     : `secret` is the base32 TOTP shared secret, encrypted
 *                with APP_KEY (sodium_crypto_secretbox + base64url).
 *   - webauthn : `webauthn_credential_id`, `webauthn_public_key`,
 *                `webauthn_sign_count`, `webauthn_aaguid`, `transports`
 *                are populated. `secret` is empty.
 */
final class MfaCredential
{
    public function __construct(private Database $db)
    {
    }

    public function countForUser(int $userId): int
    {
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS c FROM {prefix:mfa_credentials} WHERE user_id = :u',
            ['u' => $userId]
        );
        return (int)($row['c'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM {prefix:mfa_credentials} WHERE user_id = :u ORDER BY id DESC',
            ['u' => $userId]
        );
    }

    public function listByType(int $userId, string $type): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM {prefix:mfa_credentials} WHERE user_id = :u AND type = :t ORDER BY id DESC',
            ['u' => $userId, 't' => $type]
        );
    }

    public function createTotp(int $userId, string $label, string $encryptedSecret): int
    {
        return (int)$this->db->insert('mfa_credentials', [
            'user_id'    => $userId,
            'type'       => 'totp',
            'label'      => $label !== '' ? $label : 'Authenticator',
            'secret'     => $encryptedSecret,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function createWebauthn(
        int $userId,
        string $label,
        string $credentialId,
        string $publicKey,
        int $signCount,
        ?string $aaguid,
        ?string $transports
    ): int {
        return (int)$this->db->insert('mfa_credentials', [
            'user_id'                 => $userId,
            'type'                    => 'webauthn',
            'label'                   => $label !== '' ? $label : 'Security key',
            'webauthn_credential_id'  => $credentialId,
            'webauthn_public_key'     => $publicKey,
            'webauthn_sign_count'     => $signCount,
            'webauthn_aaguid'         => $aaguid,
            'transports'              => $transports,
            'created_at'              => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function deleteForUser(int $id, int $userId): bool
    {
        $stmt = $this->db->run(
            'DELETE FROM {prefix:mfa_credentials} WHERE id = :id AND user_id = :u',
            ['id' => $id, 'u' => $userId]
        );
        return $stmt->rowCount() > 0;
    }

    public function deleteAllForUser(int $userId): int
    {
        $stmt = $this->db->run(
            'DELETE FROM {prefix:mfa_credentials} WHERE user_id = :u',
            ['u' => $userId]
        );
        return $stmt->rowCount();
    }

    public function touchLastUsed(int $id): void
    {
        $this->db->update('mfa_credentials', [
            'last_used_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function setSignCount(int $id, int $count): void
    {
        $this->db->update('mfa_credentials', [
            'webauthn_sign_count' => $count,
            'last_used_at'        => gmdate('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    /**
     * Find a WebAuthn credential by its base64url-encoded credential ID.
     *
     * @return array<string, mixed>|null
     */
    public function findByCredentialId(string $credentialId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM {prefix:mfa_credentials} WHERE type = :t AND webauthn_credential_id = :cid LIMIT 1',
            ['t' => 'webauthn', 'cid' => $credentialId]
        );
    }

    public function userHasType(int $userId, string $type): bool
    {
        $row = $this->db->fetch(
            'SELECT id FROM {prefix:mfa_credentials} WHERE user_id = :u AND type = :t LIMIT 1',
            ['u' => $userId, 't' => $type]
        );
        return $row !== null;
    }
}
