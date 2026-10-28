<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id");
header("Cache-Control: no-store");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Only GET requests are allowed"]);
    exit;
}

require_once __DIR__ . "/../config/db.php";

// Resolve user identity
$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId) {
    $userId = filter_var($_GET["user_id"] ?? $_GET["id"] ?? null, FILTER_VALIDATE_INT);
}
if (!$userId && isset($_SERVER["HTTP_X_USER_ID"])) {
    $userId = filter_var($_SERVER["HTTP_X_USER_ID"], FILTER_VALIDATE_INT);
}

if (!$userId || $userId < 1) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Please log in first or specify a valid user_id"
    ]);
    exit;
}

$period = strtolower(trim($_GET["period"] ?? "week")); // 'week' or 'month'
if (!in_array($period, ["week", "month"])) {
    $period = "week";
}

try {
    // 1. All-time Breakdown Summary
    $stmtSummary = $conn->prepare(
        "SELECT 
            COUNT(*) as total_analyzed,
            SUM(CASE WHEN is_real = 1 AND confidence >= 70 THEN 1 ELSE 0 END) as real_count,
            SUM(CASE WHEN is_real = 0 AND confidence >= 70 THEN 1 ELSE 0 END) as fake_count,
            SUM(CASE WHEN confidence < 70 THEN 1 ELSE 0 END) as uncertain_count
         FROM verification_history
         WHERE user_id = :uid"
    );
    $stmtSummary->execute([":uid" => $userId]);
    $summaryData = $stmtSummary->fetch();

    $totalAnalyzed = (int)($summaryData["total_analyzed"] ?? 0);
    $realCount = (int)($summaryData["real_count"] ?? 0);
    $fakeCount = (int)($summaryData["fake_count"] ?? 0);
    $uncertainCount = (int)($summaryData["uncertain_count"] ?? 0);

    $realPct = $totalAnalyzed > 0 ? round(($realCount / $totalAnalyzed) * 100) : 0;
    $fakePct = $totalAnalyzed > 0 ? round(($fakeCount / $totalAnalyzed) * 100) : 0;
    $uncertainPct = $totalAnalyzed > 0 ? round(($uncertainCount / $totalAnalyzed) * 100) : 0;

    // Saved count
    $stmtSaved = $conn->prepare("SELECT COUNT(*) as saved_count FROM saved_verifications WHERE user_id = :uid");
    $stmtSaved->execute([":uid" => $userId]);
    $savedCount = (int)$stmtSaved->fetch()["saved_count"];

    // 2. Timeline Activity Bar Chart (Last 7 days or Last 30 days)
    $daysCount = ($period === "month") ? 30 : 7;
    $stmtActivity = $conn->prepare(
        "SELECT DATE(created_at) as scan_date, COUNT(*) as scan_count
         FROM verification_history
         WHERE user_id = :uid AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
         GROUP BY DATE(created_at)
         ORDER BY scan_date ASC"
    );
    $stmtActivity->bindValue(":uid", $userId, PDO::PARAM_INT);
    $stmtActivity->bindValue(":days", $daysCount - 1, PDO::PARAM_INT);
    $stmtActivity->execute();
    $activityRows = $stmtActivity->fetchAll(PDO::FETCH_KEY_PAIR); // ['2026-10-27' => 3, ...]

    // Build continuous chronological day map
    $days = [];
    $maxCount = 1;
    $periodTotal = 0;

    for ($i = $daysCount - 1; $i >= 0; $i--) {
        $dateStr = date("Y-m-d", strtotime("-$i days"));
        $count = isset($activityRows[$dateStr]) ? (int)$activityRows[$dateStr] : 0;
        $periodTotal += $count;
        if ($count > $maxCount) {
            $maxCount = $count;
        }

        $days[] = [
            "day" => date("D", strtotime($dateStr)), // e.g. "Mon"
            "date" => $dateStr,
            "count" => $count,
            "isToday" => ($dateStr === date("Y-m-d"))
        ];
    }

    // Assign normalized height fraction (0.15f min if count > 0, up to 1.0f max)
    foreach ($days as &$d) {
        if ($d["count"] === 0) {
            $d["heightFraction"] = 0.08;
        } else {
            $d["heightFraction"] = round(0.20 + (($d["count"] / $maxCount) * 0.80), 2);
        }
    }
    unset($d);

    $dateRangeLabel = $period === "month"
        ? "$periodTotal checks · Past 30 Days"
        : "$periodTotal checks · Past 7 Days";

    // 3. Common Suspicious Patterns (from flagged fake analyses)
    $stmtWarnings = $conn->prepare(
        "SELECT warning_signals 
         FROM verification_history 
         WHERE user_id = :uid AND is_real = 0 AND warning_signals IS NOT NULL"
    );
    $stmtWarnings->execute([":uid" => $userId]);
    $warningRows = $stmtWarnings->fetchAll();

    $patternCategories = [
        "Missing primary sources" => 0,
        "Sensational headlines & urgency" => 0,
        "Unsubstantiated viral claims" => 0,
        "Source domain credibility flags" => 0
    ];

    foreach ($warningRows as $wRow) {
        $signals = json_decode($wRow["warning_signals"], true);
        if (is_array($signals)) {
            foreach ($signals as $sig) {
                $sigLower = strtolower($sig);
                if (strpos($sigLower, "source") !== false || strpos($sigLower, "press release") !== false) {
                    $patternCategories["Missing primary sources"]++;
                } elseif (strpos($sigLower, "urgency") !== false || strpos($sigLower, "sensational") !== false) {
                    $patternCategories["Sensational headlines & urgency"]++;
                } elseif (strpos($sigLower, "viral") !== false || strpos($sigLower, "unofficial") !== false) {
                    $patternCategories["Unsubstantiated viral claims"]++;
                } else {
                    $patternCategories["Source domain credibility flags"]++;
                }
            }
        }
    }

    $patternsList = [];
    $totalFakeAnalyses = max(1, $fakeCount);
    foreach ($patternCategories as $title => $cnt) {
        if ($cnt > 0 || $totalAnalyzed === 0) {
            $progress = round(min(1.0, $cnt / $totalFakeAnalyses), 2);
            $patternsList[] = [
                "title" => $title,
                "count" => $cnt,
                "label" => "$cnt checks",
                "progress" => $progress
            ];
        }
    }

    // Default friendly patterns if user has zero fakes yet
    if (empty($patternsList)) {
        $patternsList = [
            ["title" => "Missing primary sources", "count" => 0, "label" => "0 checks", "progress" => 0.0],
            ["title" => "Sensational headlines & urgency", "count" => 0, "label" => "0 checks", "progress" => 0.0],
            ["title" => "Unsubstantiated viral claims", "count" => 0, "label" => "0 checks", "progress" => 0.0]
        ];
    }

    // 4. Personalized AI Checking Habits Insight Summary
    $aiInsight = "Welcome to FactScan AI! Start verifying URLs, articles, or text claims to build your personal verification profile.";
    if ($totalAnalyzed > 0) {
        if ($realPct >= 70) {
            $aiInsight = "High factual exposure! Over $realPct% of your analyzed content is verified authentic from primary news registries.";
        } elseif ($fakePct >= 50) {
            $aiInsight = "High vigilance required: $fakePct% of your recent scans were flagged as unverified viral claims or hoaxes.";
        } else {
            $aiInsight = "Balanced digital consumption. You regularly check breaking stories before forwarding or acting on unverified claims.";
        }
    }

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "userId" => $userId,
        "period" => $period,
        "summary" => [
            "totalAnalyzed" => $totalAnalyzed,
            "realCount" => $realCount,
            "realPercentage" => $realPct,
            "fakeCount" => $fakeCount,
            "fakePercentage" => $fakePct,
            "uncertainCount" => $uncertainCount,
            "uncertainPercentage" => $uncertainPct,
            "savedArticlesCount" => $savedCount
        ],
        "activity" => [
            "periodLabel" => $dateRangeLabel,
            "periodTotal" => $periodTotal,
            "days" => $days
        ],
        "suspiciousPatterns" => $patternsList,
        "aiHabitInsight" => $aiInsight
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Personal analysis error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database error occurred while calculating personal analysis"
    ]);
}
?>