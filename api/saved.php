<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id");
header("Cache-Control: no-store");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

require_once __DIR__ . "/../config/db.php";

function sendResponse($status, $data) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = $_POST;
}

// User identification
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

try {
    // 1. GET: List Saved Articles / Verifications
    if ($method === "GET") {
        $stmt = $conn->prepare(
            "SELECT 
                s.id as bookmark_id,
                s.saved_at,
                v.id,
                v.headline,
                v.publisher,
                v.is_real,
                v.confidence,
                v.summary,
                v.input_type,
                v.warning_signals,
                v.verified_sources,
                v.created_at
             FROM saved_verifications s
             INNER JOIN verification_history v ON s.verification_id = v.id
             WHERE s.user_id = :uid
             ORDER BY s.saved_at DESC"
        );
        $stmt->execute([":uid" => $userId]);
        $rows = $stmt->fetchAll();

        $savedList = [];
        foreach ($rows as $r) {
            $createdTime = strtotime($r["created_at"]);
            $savedList[] = [
                "bookmarkId" => (int)$r["bookmark_id"],
                "id" => (int)$r["id"],
                "headline" => $r["headline"],
                "publisher" => $r["publisher"],
                "isReal" => (bool)$r["is_real"],
                "confidence" => (int)$r["confidence"],
                "summary" => $r["summary"],
                "timestamp" => date("M d, Y · g:i A", $createdTime),
                "savedAt" => $r["saved_at"],
                "inputType" => $r["input_type"],
                "warningSignals" => !empty($r["warning_signals"]) ? json_decode($r["warning_signals"], true) : [],
                "verifiedSources" => !empty($r["verified_sources"]) ? json_decode($r["verified_sources"], true) : [],
                "isSaved" => true
            ];
        }

        sendResponse(200, [
            "success" => true,
            "total" => count($savedList),
            "savedArticles" => $savedList
        ]);
    }

    // 2. POST: Save / Bookmark an article
    if ($method === "POST") {
        $verificationId = filter_var($input["verification_id"] ?? $input["id"] ?? null, FILTER_VALIDATE_INT);
        if (!$verificationId || $verificationId < 1) {
            sendResponse(400, [
                "success" => false,
                "message" => "Valid verification_id is required"
            ]);
        }

        // Verify verification exists
        $stmtVer = $conn->prepare("SELECT id FROM verification_history WHERE id = :vid LIMIT 1");
        $stmtVer->execute([":vid" => $verificationId]);
        if (!$stmtVer->fetch()) {
            sendResponse(404, [
                "success" => false,
                "message" => "Verification analysis record not found"
            ]);
        }

        $stmtSave = $conn->prepare(
            "INSERT INTO saved_verifications (user_id, verification_id, saved_at)
             VALUES (:uid, :vid, NOW())
             ON DUPLICATE KEY UPDATE saved_at = NOW()"
        );
        $stmtSave->execute([
            ":uid" => $userId,
            ":vid" => $verificationId
        ]);

        sendResponse(200, [
            "success" => true,
            "message" => "Article saved successfully to bookmarks",
            "verification_id" => $verificationId,
            "isSaved" => true
        ]);
    }

    // 3. DELETE: Remove Bookmark
    if ($method === "DELETE") {
        $verificationId = filter_var($_GET["verification_id"] ?? $_GET["id"] ?? $input["verification_id"] ?? $input["id"] ?? null, FILTER_VALIDATE_INT);
        if (!$verificationId || $verificationId < 1) {
            sendResponse(400, [
                "success" => false,
                "message" => "Valid verification_id or bookmark id is required"
            ]);
        }

        $stmtDel = $conn->prepare("DELETE FROM saved_verifications WHERE user_id = :uid AND (verification_id = :vid OR id = :vid)");
        $stmtDel->execute([":uid" => $userId, ":vid" => $verificationId]);

        if ($stmtDel->rowCount() > 0) {
            sendResponse(200, [
                "success" => true,
                "message" => "Bookmark removed successfully",
                "verification_id" => $verificationId,
                "isSaved" => false
            ]);
        } else {
            sendResponse(404, [
                "success" => false,
                "message" => "Saved article not found"
            ]);
        }
    }

    header("Allow: GET, POST, DELETE");
    sendResponse(405, ["success" => false, "message" => "Method not allowed"]);

} catch (PDOException $e) {
    error_log("Saved operation error: " . $e->getMessage());
    sendResponse(500, [
        "success" => false,
        "message" => "Database error occurred while updating bookmarks"
    ]);
}
?>
