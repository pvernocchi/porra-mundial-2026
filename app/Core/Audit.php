<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Audit log writer. Records security-sensitive events with the actor,
 * IP and user-agent.
 */
final class Audit
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function log(string $event, ?int $userId = null, array $data = [], ?string $ip = null, ?string $ua = null): void
    {
        try {
            $this->db->insert('audit_log', [
                'user_id'    => $userId,
                'event'      => $event,
                'ip'         => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? null),
                'ua'         => mb_substr((string)($ua ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255),
                'data'       => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Audit must never break the request; log to file as fallback.
            $line = sprintf("[%s] AUDIT_FAIL %s %s\n", gmdate('c'), $event, $e->getMessage());
            @file_put_contents(
                dirname(__DIR__, 2) . '/storage/logs/audit-fallback.log',
                $line,
                FILE_APPEND
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentForUser(int $userId, int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));
        return $this->db->fetchAll(
            'SELECT * FROM {prefix:audit_log} WHERE user_id = :u ORDER BY id DESC LIMIT ' . $limit,
            ['u' => $userId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return $this->db->fetchAll(
            'SELECT * FROM {prefix:audit_log} ORDER BY id DESC LIMIT ' . $limit
        );
    }
}
