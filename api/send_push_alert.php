<?php
/**
 * FactScan AI - Dispatch Push Alert to Devices
 * Endpoint: POST /api/send_push_alert.php
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once __DIR__ . "/../config/db.php";

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true) ?: $_POST;

$title = trim($data["title"] ?? "🚨 FactScan Hoax Alert");
$body = trim($data["body"] ?? "A viral claim was verified as false or misleading.");
$verdict = strtoupper(trim($data["verdict"] ?? "FAKE"));
$confidence = isset($data["confidence"]) ? (int)$data["confidence"] : 95;
$claimId = isset($data["claim_id"]) ? (int)$data["claim_id"] : null;

if (!$conn) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database connection unavailable."]);
    exit();
}

try {
    // 1. Record alert in push_notifications table
    $stmt = $conn->prepare("INSERT INTO push_notifications (title, body, verdict, confidence, claim_id) VALUES (:title, :body, :verdict, :confidence, :claim_id)");
    $stmt->execute([
        ":title" => $title,
        ":body" => $body,
        ":verdict" => $verdict,
        ":confidence" => $confidence,
        ":claim_id" => $claimId
    ]);
    $notifId = $conn->lastInsertId();

    // 2. Fetch all registered device FCM tokens
    $tokensStmt = $conn->query("SELECT DISTINCT fcm_token FROM fcm_device_tokens WHERE fcm_token IS NOT NULL AND fcm_token != ''");
    $tokens = $tokensStmt->fetchAll(PDO::FETCH_COLUMN);

    $dispatched = 0;
    $fcmServerKey = getenv("FCM_SERVER_KEY") ?: "";

    if (!empty($tokens) && !empty($fcmServerKey)) {
        // Broadcast via Legacy FCM or HTTP v1
        $url = "https://fcm.googleapis.com/fcm/send";
        $headers = [
            "Authorization: key=" . $fcmServerKey,
            "Content-Type: application/json"
        ];

        // Send in batches of 500
        $chunks = array_chunk($tokens, 500);
        foreach ($chunks as $chunk) {
            $payload = [
                "registration_ids" => $chunk,
                "notification" => [
                    "title" => $title,
                    "body" => $body,
                    "sound" => "default"
                ],
                "data" => [
                    "verdict" => $verdict,
                    "confidence" => (string)$confidence,
                    "notif_id" => (string)$notifId
                ],
                "priority" => "high"
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $res = curl_exec($ch);
            curl_close($ch);
            $dispatched += count($chunk);
        }
    }

    echo json_encode([
        "success" => true,
        "notification_id" => $notifId,
        "registered_devices" => count($tokens),
        "fcm_dispatched" => $dispatched,
        "message" => "Alert logged and queued for push delivery."
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Push dispatch error: " . $e->getMessage()
    ]);
}
