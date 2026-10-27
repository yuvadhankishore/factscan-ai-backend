<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Only POST requests are allowed"
    ]);
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

$currentPassword = $input["current_password"] ?? "";
$newPassword = $input["new_password"] ?? "";

if ($currentPassword === "" || $newPassword === "") {
    sendResponse(400, [
        "success" => false,
        "message" => "Both current_password and new_password are required"
    ]);
}

if (strlen($newPassword) < 8) {
    sendResponse(400, [
        "success" => false,
        "message" => "New password must be at least 8 characters long"
    ]);
}

try {
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([":id" => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        sendResponse(404, [
            "success" => false,
            "message" => "User not found"
        ]);
    }

    if (!password_verify($currentPassword, $user["password"])) {
        sendResponse(400, [
            "success" => false,
            "message" => "Current password is incorrect"
        ]);
    }

    if (password_verify($newPassword, $user["password"])) {
        sendResponse(400, [
            "success" => false,
            "message" => "New password cannot be the same as your old password"
        ]);
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $updateStmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :id");
    $updateStmt->execute([
        ":password" => $hashedPassword,
        ":id" => $userId
    ]);

    sendResponse(200, [
        "success" => true,
        "message" => "Password changed successfully"
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse(500, [
        "success" => false,
        "message" => "Failed to change password"
    ]);
}
?>
