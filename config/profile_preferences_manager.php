<?php
/**
 * FactScan AI - Fast Profile Preferences Manager
 * Manages user domain watchlist (Trusted/Flagged) and granular Hoax Radar topic preferences
 * with MySQL persistence and fast disk-cache fallback.
 */

class ProfilePreferencesManager {

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
    }
}
