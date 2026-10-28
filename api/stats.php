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

// Optional user identity
$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId) {
    $userId = filter_var($_GET["user_id"] ?? $_GET["id"] ?? null, FILTER_VALIDATE_INT);
}
if (!$userId && isset($_SERVER["HTTP_X_USER_ID"])) {
    $userId = filter_var($_SERVER["HTTP_X_USER_ID"], FILTER_VALIDATE_INT);
}

try {
    // Global aggregate statistics
    $stmtGlobal = $conn->query(
        "SELECT 
            COUNT(*) as total_claims,
            SUM(CASE WHEN is_real = 1 THEN 1 ELSE 0 END) as real_claims,
            SUM(CASE WHEN is_real = 0 THEN 1 ELSE 0 END) as fake_claims,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_scans
         FROM verification_history"
    );
    $global = $stmtGlobal->fetch();

    $totalClaims = (int)($global["total_claims"] ?? 0);
    $realClaims = (int)($global["real_claims"] ?? 0);
    $fakeClaims = (int)($global["fake_claims"] ?? 0);
    $todayScans = (int)($global["today_scans"] ?? 0);

    $realPercentage = $totalClaims > 0 ? round(($realClaims / $totalClaims) * 100) : 0;
    $fakePercentage = $totalClaims > 0 ? round(($fakeClaims / $totalClaims) * 100) : 0;

    $userStats = null;
    if ($userId && $userId > 0) {
        $stmtUser = $conn->prepare(
            "SELECT 
                COUNT(*) as user_total,
                SUM(CASE WHEN is_real = 1 THEN 1 ELSE 0 END) as user_real,
                SUM(CASE WHEN is_real = 0 THEN 1 ELSE 0 END) as user_fake
             FROM verification_history
             WHERE user_id = :uid"
        );
        $stmtUser->execute([":uid" => $userId]);
        $uData = $stmtUser->fetch();

        $stmtSavedCount = $conn->prepare("SELECT COUNT(*) as saved_count FROM saved_verifications WHERE user_id = :uid");
        $stmtSavedCount->execute([":uid" => $userId]);
        $savedCount = (int)$stmtSavedCount->fetch()["saved_count"];

        $userStats = [
            "totalScans" => (int)($uData["user_total"] ?? 0),
            "realScans" => (int)($uData["user_real"] ?? 0),
            "fakeScans" => (int)($uData["user_fake"] ?? 0),
            "savedArticlesCount" => $savedCount
        ];
    }

    // Recent 5 trending verifications
    $stmtRecent = $conn->query(
        "SELECT id, headline, publisher, is_real, confidence, created_at 
         FROM verification_history 
         ORDER BY created_at DESC 
         LIMIT 5"
    );
    $recentRows = $stmtRecent->fetchAll();
    $recentList = [];
    foreach ($recentRows as $row) {
        $recentList[] = [
            "id" => (int)$row["id"],
            "headline" => $row["headline"],
            "publisher" => $row["publisher"],
            "isReal" => (bool)$row["is_real"],
            "confidence" => (int)$row["confidence"],
            "timestamp" => date("M d, Y · g:i A", strtotime($row["created_at"]))
        ];
    }

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "globalStats" => [
            "totalClaimsAnalyzed" => $totalClaims,
            "realClaimsCount" => $realClaims,
            "fakeClaimsCount" => $fakeClaims,
            "realPercentage" => $realPercentage,
            "fakePercentage" => $fakePercentage,
            "todayScans" => $todayScans
        ],
        "userStats" => $userStats,
        "recentVerifications" => $recentList
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database error occurred while calculating statistics"
    ]);
}
?>
