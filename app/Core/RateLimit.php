<?php
declare(strict_types=1);

namespace App\Core;

/**
 * File-backed sliding-window rate limiter.
 *
 * Each key maps to a JSON file with a list of timestamps. We keep only
 * the timestamps inside the configured window and refuse the request
 * when the count would exceed the maximum.
 *
 * Designed for shared hosting: pure file I/O, no memcache/Redis assumed.
 */
final class RateLimit
{
    public function __construct(private string $dir)
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    /**
     * @return array{allowed:bool, remaining:int, retryAfter:int}
     */
    public function hit(string $key, int $max, int $windowSeconds): array
    {
        $file = $this->fileFor($key);
        $now  = time();
        $cutoff = $now - $windowSeconds;

        $fh = @fopen($file, 'c+');
        if ($fh === false) {
            return ['allowed' => true, 'remaining' => $max, 'retryAfter' => 0];
        }
        try {
            @flock($fh, LOCK_EX);
            $raw = stream_get_contents($fh) ?: '';
            $times = $raw === '' ? [] : (array)json_decode($raw, true);
            $times = array_values(array_filter(
                array_map('intval', $times),
                static fn(int $t) => $t >= $cutoff
            ));

            if (count($times) >= $max) {
                $retry = max(1, ($times[0] + $windowSeconds) - $now);
                return ['allowed' => false, 'remaining' => 0, 'retryAfter' => $retry];
            }

            $times[] = $now;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($times) ?: '[]');
            fflush($fh);

            return [
                'allowed'    => true,
                'remaining'  => max(0, $max - count($times)),
                'retryAfter' => 0,
            ];
        } finally {
            @flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    public function reset(string $key): void
    {
        $f = $this->fileFor($key);
        if (is_file($f)) {
            @unlink($f);
        }
    }

    private function fileFor(string $key): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . sha1($key) . '.json';
    }
}
