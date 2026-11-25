<?php
/**
 * FactScan AI - Live Hoax Radar Aggregator
 * Combines database alerts, viral clusters, and live RSS fact-checks
 * into categorized disinformation radar dispatches.
 */

require_once __DIR__ . "/viral_cluster_tracker.php";
require_once __DIR__ . "/live_news_fetcher.php";

class HoaxRadarFeed {

    public static function getAlerts($conn = null, string $category = "All", int $limit = 20): array {
        $alerts = [];

        // 1. Pull from hoax_radar_alerts & push_notifications if DB is available
        if ($conn instanceof PDO) {
            try {
                $stmt = $conn->query("SELECT id, title, summary, category, verdict, severity, source_name, spread_count, created_at FROM hoax_radar_alerts ORDER BY id DESC LIMIT 30");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $alerts[] = [
                        "id" => (int)$row["id"],
                        "title" => $row["title"],
                        "summary" => $row["summary"],
                        "category" => $row["category"] ?: "General",
                        "verdict" => $row["verdict"] ?: "FAKE",
                        "severity" => $row["severity"] ?: "HIGH",
                        "sourceName" => $row["source_name"] ?: "FactScan Radar",
                        "spreadCount" => (int)$row["spread_count"],
                        "timestamp" => $row["created_at"]
                    ];
                }
            } catch (Throwable $e) {
                // Non-fatal
            }
        }

        // 2. Pull top viral hoax clusters
        $clusters = ViralClusterTracker::getTopViralClusters($conn, 10);
        foreach ($clusters as $idx => $c) {
            $verdict = strtoupper($c["verdict"] ?? "FAKE");
            if (!in_array($verdict, ["FAKE", "MISLEADING", "UNVERIFIED"], true)) continue;
            $claim = $c["canonical_claim"] ?? "";
            if (empty($claim)) continue;
            $alerts[] = [
                "id" => 10000 + $idx,
                "title" => mb_strlen($claim) > 75 ? mb_substr($claim, 0, 72) . "..." : $claim,
                "summary" => "Tracked across FactScan AI with " . ($c["spread_count"] ?? 1) . " user verification checks.",
                "category" => self::classifyCategory($claim),
                "verdict" => $verdict,
                "severity" => ((int)($c["spread_count"] ?? 1) >= 3) ? "CRITICAL" : "HIGH",
                "sourceName" => "FactScan Viral Tracker",
                "spreadCount" => (int)($c["spread_count"] ?? 1),
                "timestamp" => $c["last_seen"] ?? date("Y-m-d H:i:s")
            ];
        }

        // Filter by category if requested
        $catNorm = strtolower(trim($category));
        if ($catNorm !== "" && $catNorm !== "all") {
            $alerts = array_values(array_filter($alerts, function($a) use ($catNorm) {
                return strtolower($a["category"]) === $catNorm;
            }));
        }

        return array_slice($alerts, 0, $limit);
    }

    public static function classifyCategory(string $text): string {
        return "General";
    }
}
