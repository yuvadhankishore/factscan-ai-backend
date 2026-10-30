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
    echo json_encode(["success" => false, "message" => "Only POST requests are allowed"]);
    exit;
}

require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    $data = $_POST;
}

// User identification (optional for feedback)
$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId) {
    $userId = filter_var($_GET["user_id"] ?? $_GET["id"] ?? null, FILTER_VALIDATE_INT);
}
if (!$userId && isset($_SERVER["HTTP_X_USER_ID"])) {
    $userId = filter_var($_SERVER["HTTP_X_USER_ID"], FILTER_VALIDATE_INT);
}
if (!$userId && isset($data["user_id"])) {
    $userId = filter_var($data["user_id"], FILTER_VALIDATE_INT);
}

$verificationId = filter_var($data["verification_id"] ?? $data["id"] ?? null, FILTER_VALIDATE_INT);
$feedbackType = strtoupper(trim($data["feedback_type"] ?? "OTHER"));
$comment = trim($data["comment"] ?? $data["message"] ?? "");

if (empty($comment)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Please provide a description or message for your inquiry/feedback"
    ]);
    exit;
}

$allowedTypes = ["AGREE", "DISAGREE", "INACCURATE_SOURCE", "OTHER"];
if (!in_array($feedbackType, $allowedTypes)) {
    $feedbackType = "OTHER";
}

try {
    // If verificationId is provided, verify it exists; otherwise fallback to latest or null
    $validVerificationId = null;
    if ($verificationId && $verificationId > 0) {
        $stmtVer = $conn->prepare("SELECT id FROM verification_history WHERE id = :vid LIMIT 1");
        $stmtVer->execute([":vid" => $verificationId]);
        if ($stmtVer->fetch()) {
            $validVerificationId = $verificationId;
        }
    }

    if (!$validVerificationId) {
        // Find latest verification record to attach inquiry or general support
        $stmtLatest = $conn->query("SELECT id FROM verification_history ORDER BY id DESC LIMIT 1");
        $latest = $stmtLatest->fetch();
        if ($latest) {
            $validVerificationId = (int)$latest["id"];
        }
    }

    // Insert feedback/ticket
    if ($validVerificationId) {
        $stmtIns = $conn->prepare(
            "INSERT INTO analysis_feedback (user_id, verification_id, feedback_type, comment, created_at)
             VALUES (:uid, :vid, :ftype, :comment, NOW())"
        );
        $stmtIns->execute([
            ":uid" => ($userId && $userId > 0) ? $userId : null,
            ":vid" => $validVerificationId,
            ":ftype" => $feedbackType,
            ":comment" => $comment
        ]);
    }

    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Thank you! Your ticket/feedback has been received. Our team will review it shortly."
    ]);

} catch (PDOException $e) {
    error_log("Feedback submission failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database error occurred while submitting feedback"
    ]);
}
?>
