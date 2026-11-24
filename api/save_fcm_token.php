<?php
/**
 * FactScan AI - Register & Update Device Push Token
 * Endpoint: POST /api/save_fcm_token.php
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once __DIR__ . "/../config/db.php";

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true) ?: $_POST;

$deviceId = trim($data["device_id"] ?? "");
$fcmToken = trim($data["fcm_token"] ?? $data["token"] ?? "");
$userId = isset($data["user_id"]) && is_numeric($data["user_id"]) ? (int)$data["user_id"] : null;
$deviceName = trim($data["device_name"] ?? "Android Device");

if (empty($deviceId) || empty($fcmToken)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "Both device_id and fcm_token are required."
    ]);
    exit();
}

if (!$conn) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Database connection unavailable."
    ]);
    exit();
}

try {
    // Upsert device token
    $sql = "INSERT INTO fcm_device_tokens (user_id, device_id, fcm_token, device_name)
            VALUES (:user_id, :device_id, :fcm_token, :device_name)
            ON DUPLICATE KEY UPDATE 
                fcm_token = VALUES(fcm_token),
                user_id = VALUES(user_id),
                device_name = VALUES(device_name),
                updated_at = CURRENT_TIMESTAMP";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ":user_id" => $userId,
        ":device_id" => $deviceId,
        ":fcm_token" => $fcmToken,
        ":device_name" => $deviceName
    ]);

    echo json_encode([
        "success" => true,
        "message" => "FCM push token registered successfully.",
        "device_id" => $deviceId
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Failed to save device token: " . $e->getMessage()
    ]);
}
