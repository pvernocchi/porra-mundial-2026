<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Password;

/**
 * User row + small repository.
 *
 * Status values:
 *   - active   : can log in
 *   - disabled : credentials valid but blocked from login
 *   - deleted  : soft-deleted; hidden from default lists
 */
final class User
{
    public int $id = 0;
    public string $fullName = '';
    public string $email = '';
    public string $passwordHash = '';
    public string $role = 'user';
    public string $status = 'active';
    public bool $mfaEnforced = false;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
    public ?string $lastLoginAt = null;
    public ?string $deletedAt = null;

    public function __construct(private Database $db)
    {
    }

    public function find(int $id): ?self
    {
        $row = $this->db->fetch(
            'SELECT * FROM {prefix:users} WHERE id = :id AND status != :s LIMIT 1',
            ['id' => $id, 's' => 'deleted']
        );
        return $row ? $this->hydrate($row) : null;
    }

    public function findByEmail(string $email): ?self
    {
        $row = $this->db->fetch(
            'SELECT * FROM {prefix:users} WHERE email = :e AND status != :s LIMIT 1',
            ['e' => strtolower($email), 's' => 'deleted']
        );
        return $row ? $this->hydrate($row) : null;
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM {prefix:users} WHERE email = :e AND status != :s';
        $params = ['e' => strtolower($email), 's' => 'deleted'];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        return $this->db->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    /**
     * @return array{0: array<int, self>, 1: int} List, total count
     */
    public function paginate(string $search = '', ?string $role = null, ?string $status = null, int $page = 1, int $perPage = 20): array
    {
        $where = ['status != :ds'];
        $params = ['ds' => 'deleted'];
        if ($search !== '') {
            $where[] = '(full_name LIKE :s OR email LIKE :s)';
            $params['s'] = '%' . $search . '%';
        }
        if ($role !== null && $role !== '') {
            $where[] = 'role = :r';
            $params['r'] = $role;
        }
        if ($status !== null && $status !== '') {
            $where[] = 'status = :st';
            $params['st'] = $status;
        }
        $sqlWhere = implode(' AND ', $where);
        $total = (int)($this->db->fetch(
            'SELECT COUNT(*) AS c FROM {prefix:users} WHERE ' . $sqlWhere, $params
        )['c'] ?? 0);

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $rows = $this->db->fetchAll(
            'SELECT * FROM {prefix:users} WHERE ' . $sqlWhere
            . ' ORDER BY id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
            $params
        );
        $list = [];
        foreach ($rows as $r) {
            $u = new self($this->db);
            $list[] = $u->hydrate($r);
        }
        return [$list, $total];
    }

    public function create(string $fullName, string $email, string $plainPassword, string $role = 'user'): int
    {
        $now = gmdate('Y-m-d H:i:s');
        return (int)$this->db->insert('users', [
            'full_name'     => $fullName,
            'email'         => strtolower($email),
            'password_hash' => Password::hash($plainPassword),
            'role'          => in_array($role, ['admin', 'account_manager'], true) ? $role : 'user',
            'status'        => 'active',
            'mfa_enforced'  => 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    public function updateProfile(int $id, string $fullName, string $role, string $status): void
    {
        $this->db->update('users', [
            'full_name'  => $fullName,
            'role'       => in_array($role, ['admin', 'account_manager'], true) ? $role : 'user',
            'status'     => in_array($status, ['active', 'disabled'], true) ? $status : 'active',
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function setPassword(int $id, string $plain): void
    {
        $this->db->update('users', [
            'password_hash' => Password::hash($plain),
            'updated_at'    => gmdate('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function softDelete(int $id): void
    {
        $this->db->update('users', [
            'status'     => 'deleted',
            'deleted_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function touchLogin(int $id): void
    {
        $this->db->update('users', [
            'last_login_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function adminCount(): int
    {
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS c FROM {prefix:users} WHERE role = :r AND status = :s',
            ['r' => 'admin', 's' => 'active']
        );
        return (int)($row['c'] ?? 0);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function hydrate(array $row): self
    {
        $u = new self($this->db);
        $u->id = (int)($row['id'] ?? 0);
        $u->fullName = (string)($row['full_name'] ?? '');
        $u->email = (string)($row['email'] ?? '');
        $u->passwordHash = (string)($row['password_hash'] ?? '');
        $u->role = (string)($row['role'] ?? 'user');
        $u->status = (string)($row['status'] ?? 'active');
        $u->mfaEnforced = (bool)($row['mfa_enforced'] ?? false);
        $u->createdAt = $row['created_at'] ?? null;
        $u->updatedAt = $row['updated_at'] ?? null;
        $u->lastLoginAt = $row['last_login_at'] ?? null;
        $u->deletedAt = $row['deleted_at'] ?? null;
        return $u;
    }
}
