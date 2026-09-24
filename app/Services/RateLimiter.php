<?php

namespace App\Services;

class RateLimiter
{
    private string $storageDir;

    public function __construct()
    {
        $this->storageDir = dirname(__DIR__, 2) . '/storage/cache/ratelimit';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $file = $this->storageDir . '/' . md5($key) . '.json';
        $data = [];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?? [];
        }

        $now = time();
        // Remove expired entries
        $data = array_filter($data, fn($ts) => $ts > $now - $decaySeconds);

        if (count($data) >= $maxAttempts) {
            return false; // rate limited
        }

        $data[] = $now;
        file_put_contents($file, json_encode(array_values($data)), LOCK_EX);
        return true;
    }

    public function clear(string $key): void
    {
        $file = $this->storageDir . '/' . md5($key) . '.json';
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
