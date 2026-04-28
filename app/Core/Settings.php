<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Settings repository backed by the `settings` table. Values are JSON
 * encoded so we can store arrays/objects transparently.
 *
 * Read-through cached in memory for the lifetime of the request.
 */
final class Settings
{
    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function __construct(private Database $db)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureLoaded();
        return array_key_exists($key, (array)$this->cache) ? $this->cache[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->ensureLoaded();
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \InvalidArgumentException('Cannot json-encode setting value for key ' . $key);
        }
        $existing = $this->db->fetch('SELECT k FROM {prefix:settings} WHERE k = :k', ['k' => $key]);
        if ($existing === null) {
            $this->db->insert('settings', ['k' => $key, 'v' => $json]);
        } else {
            $this->db->update('settings', ['v' => $json], ['k' => $key]);
        }
        $this->cache[$key] = $value;
    }

    public function forget(string $key): void
    {
        $this->ensureLoaded();
        $this->db->run('DELETE FROM {prefix:settings} WHERE k = :k', ['k' => $key]);
        unset($this->cache[$key]);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $this->ensureLoaded();
        return $this->cache ?? [];
    }

    private function ensureLoaded(): void
    {
        if ($this->cache !== null) {
            return;
        }
        $rows = $this->db->fetchAll('SELECT k, v FROM {prefix:settings}');
        $out = [];
        foreach ($rows as $r) {
            $decoded = json_decode((string)$r['v'], true);
            $out[(string)$r['k']] = $decoded;
        }
        $this->cache = $out;
    }
}
