<?php
/**
 * FactScan AI - Fetch Notification History / Alerts
 * Endpoint: GET /api/notifications.php
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/hoax_radar_feed.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true) ?: $_POST;
    $alertId = isset($data["alert_id"]) ? (int)$data["alert_id"] : 0;
    if ($conn && $alertId > 0) {
        try {
            $upd = $conn->prepare("UPDATE hoax_radar_alerts SET is_read = 1 WHERE id = :id");
            $upd->execute([":id" => $alertId]);
        } catch (Throwable $e) {}
    }
    echo json_encode(["success" => true, "marked_read" => $alertId]);
    exit();
}

$limit = isset($_GET["limit"]) ? min(50, max(1, (int)$_GET["limit"])) : 20;

if (!$conn) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Database connection unavailable."
    ]);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT id, title, body, verdict, confidence, created_at FROM push_notifications ORDER BY id DESC LIMIT :limit");
    $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll();

    foreach ($notifications as &$n) {
        $n["category"] = HoaxRadarFeed::classifyCategory(($n["title"] ?? "") . " " . ($n["body"] ?? ""));
        $n["severity"] = (strtoupper($n["verdict"] ?? "") === "FAKE") ? "HIGH" : "INFO";
    }
    unset($n);

    echo json_encode([
        "success" => true,
        "count" => count($notifications),
        "notifications" => $notifications
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Failed to fetch notifications: " . $e->getMessage()
    ]);
}
