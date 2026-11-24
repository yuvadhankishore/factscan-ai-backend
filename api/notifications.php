<?php
/**
 * FactScan AI - Fetch Notification History / Alerts
 * Endpoint: GET /api/notifications.php
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once __DIR__ . "/../config/db.php";

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
