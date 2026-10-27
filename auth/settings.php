<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, PUT, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id");
header("Cache-Control: no-store");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

require_once __DIR__ . "/../config/db.php";

function sendResponse($status, $data) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = $_POST;
}

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId) {
    $userId = filter_var($_GET["user_id"] ?? $_GET["id"] ?? null, FILTER_VALIDATE_INT);
}
if (!$userId && isset($_SERVER["HTTP_X_USER_ID"])) {
    $userId = filter_var($_SERVER["HTTP_X_USER_ID"], FILTER_VALIDATE_INT);
}
if (!$userId && isset($input["user_id"])) {
    $userId = filter_var($input["user_id"], FILTER_VALIDATE_INT);
}

if (!$userId || $userId < 1) {
    sendResponse(401, [
        "success" => false,
        "message" => "Please log in first or specify a valid user_id"
    ]);
}

$method = $_SERVER["REQUEST_METHOD"];
if ($method === "POST" && isset($input["_method"]) && strtoupper($input["_method"]) === "PUT") {
    $method = "PUT";
}

try {
    if ($method === "GET") {
        $stmt = $conn->prepare(
            "SELECT strict_verification, auto_save_history, notifications_enabled
             FROM user_settings
             WHERE user_id = :user_id
             LIMIT 1"
        );
        $stmt->execute([":user_id" => $userId]);
        $settings = $stmt->fetch();

        if (!$settings) {
            // Default settings if record does not exist yet
            $settings = [
                "strict_verification" => 1,
                "auto_save_history" => 1,
                "notifications_enabled" => 1
            ];
        }

        sendResponse(200, [
            "success" => true,
            "user_id" => $userId,
            "settings" => [
                "strict_verification" => (bool)$settings["strict_verification"],
                "auto_save_history" => (bool)$settings["auto_save_history"],
                "notifications_enabled" => (bool)$settings["notifications_enabled"]
            ]
        ]);
    }

    if ($method === "PUT" || $method === "POST") {
        // Retrieve current settings
        $stmtCur = $conn->prepare(
            "SELECT strict_verification, auto_save_history, notifications_enabled
             FROM user_settings
             WHERE user_id = :user_id
             LIMIT 1"
        );
        $stmtCur->execute([":user_id" => $userId]);
        $cur = $stmtCur->fetch();

        $curStrict = $cur ? (int)$cur["strict_verification"] : 1;
        $curAutosave = $cur ? (int)$cur["auto_save_history"] : 1;
        $curNotifications = $cur ? (int)$cur["notifications_enabled"] : 1;

        $newStrict = isset($input["strict_verification"]) ? (int)(bool)$input["strict_verification"] : $curStrict;
        $newAutosave = isset($input["auto_save_history"]) ? (int)(bool)$input["auto_save_history"] : $curAutosave;
        $newNotifications = isset($input["notifications_enabled"]) ? (int)(bool)$input["notifications_enabled"] : $curNotifications;

        $stmtSave = $conn->prepare(
            "INSERT INTO user_settings (user_id, strict_verification, auto_save_history, notifications_enabled)
             VALUES (:user_id, :strict, :autosave, :notifications)
             ON DUPLICATE KEY UPDATE
                strict_verification = VALUES(strict_verification),
                auto_save_history = VALUES(auto_save_history),
                notifications_enabled = VALUES(notifications_enabled)"
        );
        $stmtSave->execute([
            ":user_id" => $userId,
            ":strict" => $newStrict,
            ":autosave" => $newAutosave,
            ":notifications" => $newNotifications
        ]);

        sendResponse(200, [
            "success" => true,
            "message" => "Settings updated successfully",
            "user_id" => $userId,
            "settings" => [
                "strict_verification" => (bool)$newStrict,
                "auto_save_history" => (bool)$newAutosave,
                "notifications_enabled" => (bool)$newNotifications
            ]
        ]);
    }

    header("Allow: GET, PUT, POST");
    sendResponse(405, [
        "success" => false,
        "message" => "Method not allowed"
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse(500, [
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>
