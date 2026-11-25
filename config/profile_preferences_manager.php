<?php
/**
 * FactScan AI - Fast Profile Preferences Manager
 * Manages user domain watchlist (Trusted/Flagged) and granular Hoax Radar topic preferences
 * with MySQL persistence and fast disk-cache fallback.
 */

class ProfilePreferencesManager {

    private static array $memoryWatchlistCache = [];
    private static array $memoryRadarCache = [];

    public static function normalizeDomain(string $raw): string {
        $clean = trim(mb_strtolower($raw, 'UTF-8'));
        $clean = preg_replace('/^https?:\/\//i', '', $clean);
        $clean = preg_replace('/^www\./i', '', $clean);
        $clean = explode('/', $clean)[0];
        $clean = explode(':', $clean)[0];
        return trim($clean);
    }

    public static function getDomainWatchlist(int $userId, $conn = null): array {
        $trusted = [];
        $flagged = [];

        if ($conn instanceof PDO) {
            try {
                $conn->exec("CREATE TABLE IF NOT EXISTS user_domain_watchlist (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL DEFAULT 0,
                    domain VARCHAR(255) NOT NULL,
                    rule_type ENUM('TRUSTED','FLAGGED') NOT NULL DEFAULT 'TRUSTED',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_user_domain (user_id, domain)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $stmt = $conn->prepare("SELECT domain, rule_type FROM user_domain_watchlist WHERE user_id = :uid ORDER BY id DESC");
                $stmt->execute([':uid' => $userId]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if ($row['rule_type'] === 'FLAGGED') {
                        $flagged[] = $row['domain'];
                    } else {
                        $trusted[] = $row['domain'];
                    }
                }
                return ['trusted' => $trusted, 'flagged' => $flagged];
            } catch (Throwable $e) {
                // Fall through to disk fallback
            }
        }

        return self::readWatchlistDisk($userId);
    }

    public static function upsertDomainRule(int $userId, string $rawDomain, string $ruleType, $conn = null): array {
        $domain = self::normalizeDomain($rawDomain);
        $rule = strtoupper(trim($ruleType)) === 'FLAGGED' ? 'FLAGGED' : 'TRUSTED';
        if (empty($domain) || strpos($domain, '.') === false) {
            return ['success' => false, 'error' => 'Invalid domain format'];
        }

        if ($conn instanceof PDO) {
            try {
                $stmt = $conn->prepare("INSERT INTO user_domain_watchlist (user_id, domain, rule_type) VALUES (:uid, :dom, :rt)
                    ON DUPLICATE KEY UPDATE rule_type = VALUES(rule_type)");
                $stmt->execute([':uid' => $userId, ':dom' => $domain, ':rt' => $rule]);
            } catch (Throwable $e) {
                // Disk fallback below
            }
        }

        $data = self::readWatchlistDisk($userId);
        $data['trusted'] = array_values(array_diff($data['trusted'], [$domain]));
        $data['flagged'] = array_values(array_diff($data['flagged'], [$domain]));
        if ($rule === 'FLAGGED') {
            array_unshift($data['flagged'], $domain);
        } else {
            array_unshift($data['trusted'], $domain);
        }
        self::writeWatchlistDisk($userId, $data);

        return ['success' => true, 'domain' => $domain, 'ruleType' => $rule, 'watchlist' => $data];
    }

    public static function removeDomainRule(int $userId, string $rawDomain, $conn = null): array {
        $domain = self::normalizeDomain($rawDomain);
        if ($conn instanceof PDO) {
            try {
                $stmt = $conn->prepare("DELETE FROM user_domain_watchlist WHERE user_id = :uid AND domain = :dom");
                $stmt->execute([':uid' => $userId, ':dom' => $domain]);
            } catch (Throwable $e) {}
        }
        $data = self::readWatchlistDisk($userId);
        $data['trusted'] = array_values(array_diff($data['trusted'], [$domain]));
        $data['flagged'] = array_values(array_diff($data['flagged'], [$domain]));
        self::writeWatchlistDisk($userId, $data);
        return ['success' => true, 'watchlist' => $data];
    }

    private static function readWatchlistDisk(int $userId): array {
        $file = dirname(__DIR__) . "/storage/cache/watchlist_{$userId}.json";
        if (!file_exists($file)) {
            return ['trusted' => ['reuters.com', 'apnews.com', 'who.int'], 'flagged' => []];
        }
        $decoded = @json_decode(file_get_contents($file), true);
        return [
            'trusted' => array_values($decoded['trusted'] ?? []),
            'flagged' => array_values($decoded['flagged'] ?? [])
        ];
    }

    private static function writeWatchlistDisk(int $userId, array $data): void {
        $dir = dirname(__DIR__) . "/storage/cache";
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        @file_put_contents($dir . "/watchlist_{$userId}.json", json_encode($data, JSON_UNESCAPED_UNICODE));
        self::$memoryWatchlistCache[$userId] = $data;
    }

    /**
     * O(1) lookup to check if a URL or domain matches the user's personal watchlist.
     */
    public static function checkDomainStatus(int $userId, string $urlOrDomain, $conn = null): string {
        $domain = self::normalizeDomain($urlOrDomain);
        if (empty($domain)) return "NEUTRAL";

        if (!isset(self::$memoryWatchlistCache[$userId])) {
            self::$memoryWatchlistCache[$userId] = self::getDomainWatchlist($userId, $conn);
        }
        $list = self::$memoryWatchlistCache[$userId];
        $trustedSet = array_flip($list['trusted'] ?? []);
        $flaggedSet = array_flip($list['flagged'] ?? []);

        if (isset($flaggedSet[$domain])) return "USER_FLAGGED";
        if (isset($trustedSet[$domain])) return "USER_TRUSTED";
        return "NEUTRAL";
    }

    public static function getRadarTopics(int $userId, $conn = null): array {
        if (isset(self::$memoryRadarCache[$userId])) {
            return self::$memoryRadarCache[$userId];
        }
        $default = ["Health", "Finance", "Politics", "Tech"];
        if ($conn instanceof PDO) {
            try {
                $conn->exec("CREATE TABLE IF NOT EXISTS user_radar_preferences (
                    user_id INT PRIMARY KEY,
                    topics_json VARCHAR(500) NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $stmt = $conn->prepare("SELECT topics_json FROM user_radar_preferences WHERE user_id = :uid LIMIT 1");
                $stmt->execute([':uid' => $userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['topics_json'])) {
                    $decoded = @json_decode($row['topics_json'], true);
                    if (is_array($decoded)) return array_values($decoded);
                }
            } catch (Throwable $e) {}
        }
        $file = dirname(__DIR__) . "/storage/cache/radar_prefs_{$userId}.json";
        if (file_exists($file)) {
            $decoded = @json_decode(file_get_contents($file), true);
            if (is_array($decoded)) return array_values($decoded);
        }
        return $default;
    }

    public static function saveRadarTopics(int $userId, array $topics, $conn = null): array {
        $allowed = ["Health", "Finance", "Politics", "Tech", "General"];
        $clean = array_values(array_intersect($allowed, array_map('strval', $topics)));
        $json = json_encode($clean);

        if ($conn instanceof PDO) {
            try {
                $stmt = $conn->prepare("INSERT INTO user_radar_preferences (user_id, topics_json) VALUES (:uid, :tj)
                    ON DUPLICATE KEY UPDATE topics_json = VALUES(topics_json)");
                $stmt->execute([':uid' => $userId, ':tj' => $json]);
            } catch (Throwable $e) {}
        }
        $dir = dirname(__DIR__) . "/storage/cache";
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        @file_put_contents($dir . "/radar_prefs_{$userId}.json", $json);
        self::$memoryRadarCache[$userId] = $clean;
        return $clean;
    }
}
