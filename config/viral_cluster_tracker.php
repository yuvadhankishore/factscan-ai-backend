<?php
/**
 * FactScan AI - Viral Hoax Cluster & Spread Tracker
 * Detects coordinated viral rumors by normalizing claim text, computing
 * keyword fingerprints, and tracking submission frequency across users.
 */

class ViralClusterTracker {

    private static array $stopWords = [
        'the', 'is', 'at', 'which', 'on', 'a', 'an', 'and', 'or', 'in', 'that',
        'this', 'to', 'from', 'with', 'by', 'for', 'of', 'are', 'was', 'were',
        'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did',
        'breaking', 'urgent', 'forward', 'share', 'viral', 'news', 'alert',
        'claims', 'claim', 'says', 'said', 'report', 'reportedly'
    ];

    /**
     * Normalizes raw claim text into sorted, deduplicated significant tokens.
     */
    public static function tokenizeClaim(string $text): array {
        $clean = mb_strtolower($text, 'UTF-8');
        // Strip URLs
        $clean = preg_replace('/https?:\/\/\S+/i', ' ', $clean);
        // Keep only alphanumeric and spaces
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $clean);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        if (empty($clean)) {
            return [];
        }

        $words = explode(' ', $clean);
        $tokens = [];
        foreach ($words as $word) {
            if (mb_strlen($word) >= 3 && !in_array($word, self::$stopWords, true)) {
                $tokens[] = $word;
            }
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);
        return array_slice($tokens, 0, 12);
    }

    /**
     * Computes Jaccard similarity between two token sets (0.0 to 1.0).
     */
    public static function computeSimilarity(array $tokensA, array $tokensB): float {
        if (empty($tokensA) || empty($tokensB)) {
            return 0.0;
        }
        $intersection = count(array_intersect($tokensA, $tokensB));
        $union = count(array_unique(array_merge($tokensA, $tokensB)));
        return $union > 0 ? ($intersection / $union) : 0.0;
    }

    /**
     * Generates a stable SHA-256 fingerprint from sorted claim tokens.
     */
    public static function computeFingerprint(string $text): array {
        $tokens = self::tokenizeClaim($text);
        $keywordStr = implode(' ', $tokens);
        $fingerprint = hash('sha256', $keywordStr ?: mb_strtolower(trim($text)));
        return [
            'fingerprint' => $fingerprint,
            'keywords' => $keywordStr,
            'tokens' => $tokens
        ];
    }

    /**
     * Tracks claim spread in MySQL (with JSON disk fallback) and returns spread metadata.
     */
    public static function trackClaimSpread(string $claimText, string $verdict = 'UNVERIFIED', $conn = null): array {
        $fpData = self::computeFingerprint($claimText);
        $fingerprint = $fpData['fingerprint'];
        $keywords = $fpData['keywords'];
        $tokens = $fpData['tokens'];
        $spreadCount = 1;

        if (empty($tokens)) {
            return [
                'fingerprint' => $fingerprint,
                'viralSpreadCount' => 1,
                'isViralHoax' => false,
                'clusterWarning' => ''
            ];
        }

        // Try MySQL tracking first
        $dbTracked = false;
        if ($conn instanceof PDO) {
            try {
                // Ensure table exists gracefully
                $conn->exec("CREATE TABLE IF NOT EXISTS viral_hoax_clusters (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    fingerprint VARCHAR(64) NOT NULL UNIQUE,
                    canonical_claim TEXT NOT NULL,
                    keywords VARCHAR(255) NOT NULL,
                    verdict VARCHAR(50) DEFAULT 'FAKE',
                    spread_count INT NOT NULL DEFAULT 1,
                    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $stmt = $conn->prepare("SELECT id, spread_count, keywords FROM viral_hoax_clusters WHERE fingerprint = :fp LIMIT 1");
                $stmt->execute([':fp' => $fingerprint]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $spreadCount = (int)$existing['spread_count'] + 1;
                    $upd = $conn->prepare("UPDATE viral_hoax_clusters SET spread_count = :cnt, verdict = :v WHERE id = :id");
                    $upd->execute([':cnt' => $spreadCount, ':v' => $verdict, ':id' => $existing['id']]);
                } else {
                    // Check recent clusters for near-duplicate Jaccard match (>= 0.65)
                    $recentStmt = $conn->query("SELECT id, spread_count, keywords FROM viral_hoax_clusters ORDER BY last_seen DESC LIMIT 50");
                    $matchedId = null;
                    foreach ($recentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $rowTokens = explode(' ', $row['keywords']);
                        if (self::computeSimilarity($tokens, $rowTokens) >= 0.65) {
                            $matchedId = $row['id'];
                            $spreadCount = (int)$row['spread_count'] + 1;
                            break;
                        }
                    }

                    if ($matchedId !== null) {
                        $upd = $conn->prepare("UPDATE viral_hoax_clusters SET spread_count = :cnt, verdict = :v WHERE id = :id");
                        $upd->execute([':cnt' => $spreadCount, ':v' => $verdict, ':id' => $matchedId]);
                    } else {
                        $ins = $conn->prepare("INSERT INTO viral_hoax_clusters (fingerprint, canonical_claim, keywords, verdict, spread_count) VALUES (:fp, :claim, :kw, :v, 1)");
                        $ins->execute([
                            ':fp' => $fingerprint,
                            ':claim' => mb_substr($claimText, 0, 500),
                            ':kw' => mb_substr($keywords, 0, 250),
                            ':v' => $verdict
                        ]);
                    }
                }
                $dbTracked = true;
            } catch (Throwable $e) {
                $dbTracked = false;
            }
        }

        if (!$dbTracked) {
            $spreadCount = self::trackDiskFallback($fingerprint, $claimText, $keywords, $verdict, $tokens);
        }

        return self::buildSpreadResponse($fingerprint, $spreadCount, $verdict);
    }

    private static function trackDiskFallback(string $fingerprint, string $claimText, string $keywords, string $verdict, array $tokens): int {
        $cacheFile = dirname(__DIR__) . "/storage/cache/viral_clusters.json";
        if (!is_dir(dirname($cacheFile))) {
            @mkdir(dirname($cacheFile), 0777, true);
        }
        $clusters = file_exists($cacheFile) ? (@json_decode(file_get_contents($cacheFile), true) ?: []) : [];

        $spreadCount = 1;
        $matchedKey = null;
        if (isset($clusters[$fingerprint])) {
            $matchedKey = $fingerprint;
        } else {
            foreach ($clusters as $fp => $entry) {
                $entryTokens = explode(' ', $entry['keywords'] ?? '');
                if (self::computeSimilarity($tokens, $entryTokens) >= 0.65) {
                    $matchedKey = $fp;
                    break;
                }
            }
        }

        if ($matchedKey !== null) {
            $spreadCount = (int)($clusters[$matchedKey]['spread_count'] ?? 1) + 1;
            $clusters[$matchedKey]['spread_count'] = $spreadCount;
            $clusters[$matchedKey]['verdict'] = $verdict;
            $clusters[$matchedKey]['last_seen'] = time();
        } else {
            $clusters[$fingerprint] = [
                'fingerprint' => $fingerprint,
                'canonical_claim' => mb_substr($claimText, 0, 500),
                'keywords' => mb_substr($keywords, 0, 250),
                'verdict' => $verdict,
                'spread_count' => 1,
                'last_seen' => time()
            ];
        }

        // Keep file bounded to 200 most recent clusters
        if (count($clusters) > 200) {
            uasort($clusters, fn($a, $b) => ($b['last_seen'] ?? 0) <=> ($a['last_seen'] ?? 0));
            $clusters = array_slice($clusters, 0, 200, true);
        }
        @file_put_contents($cacheFile, json_encode($clusters, JSON_UNESCAPED_UNICODE));
        return $spreadCount;
    }

    public static function buildSpreadResponse(string $fingerprint, int $spreadCount, string $verdict): array {
        $isFakeOrMisleading = in_array(strtoupper($verdict), ['FAKE', 'MISLEADING', 'FALSE'], true);
        $isViral = ($spreadCount >= 2 && $isFakeOrMisleading);
        $warning = '';
        if ($spreadCount >= 2 && $isFakeOrMisleading) {
            $warning = "Viral Alert: Similar variations of this claim were flagged {$spreadCount} times across FactScan AI.";
        } elseif ($spreadCount >= 3) {
            $warning = "Trending Claim: Checked {$spreadCount} times recently by FactScan AI users.";
        }
        return [
            'fingerprint' => $fingerprint,
            'viralSpreadCount' => $spreadCount,
            'isViralHoax' => $isViral,
            'clusterWarning' => $warning
        ];
    }

    /**
     * Returns top viral clusters ordered by spread count.
     */
    public static function getTopViralClusters($conn = null, int $limit = 15): array {
        if ($conn instanceof PDO) {
            try {
                $stmt = $conn->prepare("SELECT fingerprint, canonical_claim, keywords, verdict, spread_count, last_seen FROM viral_hoax_clusters ORDER BY spread_count DESC, last_seen DESC LIMIT :lim");
                $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($rows)) return $rows;
            } catch (Throwable $e) {
                // Fall through to disk fallback
            }
        }
        $cacheFile = dirname(__DIR__) . "/storage/cache/viral_clusters.json";
        if (!file_exists($cacheFile)) return [];
        $clusters = @json_decode(file_get_contents($cacheFile), true) ?: [];
        uasort($clusters, fn($a, $b) => ($b['spread_count'] ?? 0) <=> ($a['spread_count'] ?? 0));
        return array_values(array_slice($clusters, 0, $limit));
    }

    /**
     * Auto-logs a radar/notification alert when a fake claim crosses a viral threshold.
     */
    public static function recordSpikeAlertIfNeeded(string $claimText, string $verdict, int $spreadCount, $conn = null): void {
        if ($spreadCount < 3 || !in_array(strtoupper($verdict), ['FAKE', 'MISLEADING'], true)) {
            return;
        }
        if ($conn instanceof PDO) {
            try {
                $title = "Viral Hoax Spike Detected ($spreadCount reports)";
                $summary = mb_substr(trim($claimText), 0, 180);
                $chk = $conn->prepare("SELECT id FROM hoax_radar_alerts WHERE title = :t AND created_at > DATE_SUB(NOW(), INTERVAL 6 HOUR) LIMIT 1");
                $chk->execute([':t' => $title]);
                if (!$chk->fetch()) {
                    $ins = $conn->prepare("INSERT INTO hoax_radar_alerts (title, summary, category, verdict, severity, spread_count) VALUES (:t, :s, 'General', :v, 'CRITICAL', :sc)");
                    $ins->execute([':t' => $title, ':s' => $summary, ':v' => $verdict, ':sc' => $spreadCount]);

                    $pushIns = $conn->prepare("INSERT INTO push_notifications (title, body, verdict, confidence) VALUES (:t, :s, :v, 95)");
                    $pushIns->execute([':t' => "🚨 " . $title, ':s' => $summary, ':v' => $verdict]);
                }
            } catch (Throwable $e) {
                // Non-fatal
            }
        }
    }

    /**
     * Deduplicates alert lists by normalized claim prefix to keep radar feeds clean.
     */
    public static function deduplicateAlerts(array $alerts): array {
        $seen = [];
        $unique = [];
        foreach ($alerts as $alert) {
            $key = mb_strtolower(preg_replace('/\s+/', '', mb_substr($alert['title'] ?? '', 0, 40)));
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $alert;
            }
        }
        return $unique;
    }
}
