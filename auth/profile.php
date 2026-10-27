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

// Support session auth, Header (X-User-Id), Query param (?user_id=X), or body (user_id)
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

// Treat POST as PUT if specified via query/header or if fields indicate an update
if ($method === "POST" && (isset($input["_method"]) && strtoupper($input["_method"]) === "PUT")) {
    $method = "PUT";
}

try {
    if ($method === "GET") {
        $stmt = $conn->prepare(
            "SELECT 
                u.id, 
                u.fullname, 
                u.email, 
                u.created_at,
                p.language, 
                p.accent_color,
                s.strict_verification, 
                s.auto_save_history, 
                s.notifications_enabled
             FROM users u
             LEFT JOIN user_profiles p ON u.id = p.user_id
             LEFT JOIN user_settings s ON u.id = s.user_id
             WHERE u.id = :id
             LIMIT 1"
        );

        $stmt->execute([":id" => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            sendResponse(404, [
                "success" => false,
                "message" => "User not found"
            ]);
        }

        sendResponse(200, [
            "success" => true,
            "profile" => [
                "id" => (int)$user["id"],
                "fullname" => $user["fullname"],
                "email" => $user["email"],
                "language" => $user["language"] ?? "English",
                "accent_color" => $user["accent_color"] ?? "blue",
                "created_at" => $user["created_at"],
                "settings" => [
                    "strict_verification" => $user["strict_verification"] === null ? true : (bool)$user["strict_verification"],
                    "auto_save_history" => $user["auto_save_history"] === null ? true : (bool)$user["auto_save_history"],
                    "notifications_enabled" => $user["notifications_enabled"] === null ? true : (bool)$user["notifications_enabled"]
                ]
            ]
        ]);
    }

    if ($method === "PUT" || $method === "POST") {
        // Fetch current user data
        $stmtCur = $conn->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmtCur->execute([":id" => $userId]);
        $currentUser = $stmtCur->fetch();

        if (!$currentUser) {
            sendResponse(404, [
                "success" => false,
                "message" => "User not found"
            ]);
        }

        $fullname = isset($input["fullname"]) ? trim($input["fullname"]) : (isset($input["full_name"]) ? trim($input["full_name"]) : $currentUser["fullname"]);
        $email = isset($input["email"]) ? trim($input["email"]) : $currentUser["email"];
        $language = isset($input["language"]) ? trim($input["language"]) : null;
        $accentColor = isset($input["accent_color"]) ? trim($input["accent_color"]) : null;

        if ($fullname === "" || strlen($fullname) > 100) {
            sendResponse(422, [
                "success" => false,
                "message" => "Enter a valid full name (1-100 characters)"
            ]);
        }

        if ($email === "" || strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendResponse(422, [
                "success" => false,
                "message" => "Enter a valid email address"
            ]);
        }

        $conn->beginTransaction();

        // Update users table
        $stmtUpUser = $conn->prepare(
            "UPDATE users
             SET fullname = :fullname, email = :email
             WHERE id = :id"
        );
        $stmtUpUser->execute([
            ":fullname" => $fullname,
            ":email" => $email,
            ":id" => $userId
        ]);

        // Update or insert user_profiles table
        $stmtUpProfile = $conn->prepare(
            "INSERT INTO user_profiles (user_id, full_name, email, language, accent_color)
             VALUES (:user_id, :full_name, :email, COALESCE(:language, 'English'), COALESCE(:accent_color, 'blue'))
             ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                email = VALUES(email),
                language = COALESCE(:language_up, language),
                accent_color = COALESCE(:accent_color_up, accent_color)"
        );
        $stmtUpProfile->execute([
            ":user_id" => $userId,
            ":full_name" => $fullname,
            ":email" => $email,
            ":language" => $language,
            ":accent_color" => $accentColor,
            ":language_up" => $language,
            ":accent_color_up" => $accentColor
        ]);

        // Check if settings were sent in request as well
        $settingsInput = $input["settings"] ?? null;
        $strict = $settingsInput["strict_verification"] ?? $input["strict_verification"] ?? null;
        $autosave = $settingsInput["auto_save_history"] ?? $input["auto_save_history"] ?? null;
        $notifications = $settingsInput["notifications_enabled"] ?? $input["notifications_enabled"] ?? null;

        if ($strict !== null || $autosave !== null || $notifications !== null) {
            $stmtUpSettings = $conn->prepare(
                "INSERT INTO user_settings (user_id, strict_verification, auto_save_history, notifications_enabled)
                 VALUES (:user_id, COALESCE(:strict, 1), COALESCE(:autosave, 1), COALESCE(:notifications, 1))
                 ON DUPLICATE KEY UPDATE
                    strict_verification = COALESCE(:strict_up, strict_verification),
                    auto_save_history = COALESCE(:autosave_up, auto_save_history),
                    notifications_enabled = COALESCE(:notifications_up, notifications_enabled)"
            );
            $stmtUpSettings->execute([
                ":user_id" => $userId,
                ":strict" => $strict !== null ? (int)$strict : null,
                ":autosave" => $autosave !== null ? (int)$autosave : null,
                ":notifications" => $notifications !== null ? (int)$notifications : null,
                ":strict_up" => $strict !== null ? (int)$strict : null,
                ":autosave_up" => $autosave !== null ? (int)$autosave : null,
                ":notifications_up" => $notifications !== null ? (int)$notifications : null
            ]);
        }

        $conn->commit();

        if (isset($_SESSION["user_id"]) && $_SESSION["user_id"] == $userId) {
            $_SESSION["fullname"] = $fullname;
            $_SESSION["email"] = $email;
        }

        // Fetch updated complete profile
        $stmtFetch = $conn->prepare(
            "SELECT 
                u.id, 
                u.fullname, 
                u.email, 
                u.created_at,
                p.language, 
                p.accent_color,
                s.strict_verification, 
                s.auto_save_history, 
                s.notifications_enabled
             FROM users u
             LEFT JOIN user_profiles p ON u.id = p.user_id
             LEFT JOIN user_settings s ON u.id = s.user_id
             WHERE u.id = :id
             LIMIT 1"
        );
        $stmtFetch->execute([":id" => $userId]);
        $updated = $stmtFetch->fetch();

        sendResponse(200, [
            "success" => true,
            "message" => "Profile updated successfully",
            "profile" => [
                "id" => (int)$updated["id"],
                "fullname" => $updated["fullname"],
                "email" => $updated["email"],
                "language" => $updated["language"] ?? "English",
                "accent_color" => $updated["accent_color"] ?? "blue",
                "created_at" => $updated["created_at"],
                "settings" => [
                    "strict_verification" => (bool)$updated["strict_verification"],
                    "auto_save_history" => (bool)$updated["auto_save_history"],
                    "notifications_enabled" => (bool)$updated["notifications_enabled"]
                ]
            ]
        ]);
    }

    header("Allow: GET, PUT, POST");
    sendResponse(405, [
        "success" => false,
        "message" => "Method not allowed"
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log($e->getMessage());

    if ($e->getCode() === "23000") {
        sendResponse(409, [
            "success" => false,
            "message" => "This email is already in use by another account"
        ]);
    }

    sendResponse(500, [
        "success" => false,
        "message" => "Database operation failed: " . $e->getMessage()
    ]);
}
?>
