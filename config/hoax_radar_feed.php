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

        // 3. Pull live fact-check dispatches from RSS wire so radar is always fresh
        $liveDebunks = LiveNewsFetcher::fetchLiveWireNews("fact check debunked hoax misinformation", 6);
        foreach ($liveDebunks as $idx => $art) {
            $title = $art["title"] ?? "";
            if (empty($title)) continue;
            $cat = self::classifyCategory($title);
            $alerts[] = [
                "id" => 20000 + $idx,
                "title" => $title,
                "summary" => "Verified fact-check dispatch from " . ($art["publisher"] ?? "News Wire") . ". Tap to inspect or verify.",
                "category" => $cat,
                "verdict" => "MISLEADING",
                "severity" => "MEDIUM",
                "sourceName" => $art["publisher"] ?? "Live Fact Wire",
                "spreadCount" => 1,
                "timestamp" => $art["publishedAt"] ?? date("M d, Y")
            ];
        }

        $alerts = ViralClusterTracker::deduplicateAlerts($alerts);

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
        $lower = mb_strtolower($text, 'UTF-8');
        if (preg_match('/\b(cancer|vaccine|cure|virus|health|doctor|medicine|lemon|covid|hospital|who|disease|fda|pill|diet)\b/i', $lower)) {
            return "Health";
        }
        if (preg_match('/\b(bank|crypto|bitcoin|rupee|dollar|stock|tax|scam|upi|atm|loan|investment|money|rbi|fed)\b/i', $lower)) {
            return "Finance";
        }
        if (preg_match('/\b(election|president|prime minister|parliament|vote|senate|government|minister|law|policy|treaty|border)\b/i', $lower)) {
            return "Politics";
        }
        if (preg_match('/\b(ai|deepfake|apple|google|meta|whatsapp|iphone|android|cyber|hack|5g|satellite|nasa|space|robot)\b/i', $lower)) {
            return "Tech";
        }
        return "General";
    }
}
