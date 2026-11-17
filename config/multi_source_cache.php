<?php
/**
 * MultiSourceCache - High-performance caching layer for external news scraping and consensus
 * Caches synthesized queries, article bodies, and stance evaluations to avoid redundant network hits.
 */

class MultiSourceCache {
    private static string $cacheDir = __DIR__ . "/../storage/cache/";
    private static int $defaultTtl = 3600; // 1 hour

    private static function init(): void {
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0777, true);
        }
    }

    private static function getCacheKey(string $claim): string {
        $normalized = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $claim)));
        return md5($normalized);
    }

    public static function get(string $claim): ?array {
        self::init();
        $key = self::getCacheKey($claim);
        $file = self::$cacheDir . "ms_" . $key . ".json";

        if (!file_exists($file)) {
            return null;
        }

        $data = @json_decode(file_get_contents($file), true);
        if (!$data || !isset($data["expires_at"])) {
            return null;
        }

        if (time() > $data["expires_at"]) {
            @unlink($file);
            return null;
        }

        return $data["payload"] ?? null;
    }

    public static function set(string $claim, array $payload, int $ttl = null): void {
        self::init();
        $ttl = $ttl ?? self::$defaultTtl;
        $key = self::getCacheKey($claim);
        $file = self::$cacheDir . "ms_" . $key . ".json";

        $data = [
            "cached_at" => time(),
            "expires_at" => time() + $ttl,
            "claim" => substr($claim, 0, 200),
            "payload" => $payload
        ];

        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public static function clearExpired(): int {
        self::init();
        $files = glob(self::$cacheDir . "ms_*.json");
        $cleared = 0;
        $now = time();

        if ($files) {
            foreach ($files as $f) {
                $data = @json_decode(file_get_contents($f), true);
                if ($data && isset($data["expires_at"]) && $now > $data["expires_at"]) {
                    @unlink($f);
                    $cleared++;
                }
            }
        }
        return $cleared;
    }
}
?>
