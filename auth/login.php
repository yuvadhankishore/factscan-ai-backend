<?php
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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    $data = $_POST;
}

$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";

if ($email === "" || $password === "") {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Email and password are required"
    ]);
    exit;
}

try {
    $stmt = $conn->prepare(
        "SELECT 
            u.id, 
            u.fullname, 
            u.email, 
            u.password, 
            u.created_at,
            p.language, 
            p.accent_color,
            s.strict_verification, 
            s.auto_save_history, 
            s.notifications_enabled
         FROM users u
         LEFT JOIN user_profiles p ON u.id = p.user_id
         LEFT JOIN user_settings s ON u.id = s.user_id
         WHERE u.email = :email
         LIMIT 1"
    );

    $stmt->execute([":email" => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user["password"])) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Invalid email or password"
        ]);
        exit;
    }

    $userId = (int)$user["id"];

    // Ensure default profile exists if it was null
    if ($user["language"] === null) {
        $stmtInsProfile = $conn->prepare(
            "INSERT INTO user_profiles (user_id, full_name, email, language, accent_color)
             VALUES (:uid, :name, :email, 'English', 'blue')
             ON DUPLICATE KEY UPDATE full_name = VALUES(full_name)"
        );
        $stmtInsProfile->execute([
            ":uid" => $userId,
            ":name" => $user["fullname"],
            ":email" => $user["email"]
        ]);
        $user["language"] = "English";
        $user["accent_color"] = "blue";
    }

    // Ensure default settings exist if null
    if ($user["strict_verification"] === null) {
        $stmtInsSettings = $conn->prepare(
            "INSERT INTO user_settings (user_id, strict_verification, auto_save_history, notifications_enabled)
             VALUES (:uid, 1, 1, 1)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)"
        );
        $stmtInsSettings->execute([":uid" => $userId]);
        $user["strict_verification"] = 1;
        $user["auto_save_history"] = 1;
        $user["notifications_enabled"] = 1;
    }

    session_regenerate_id(true);
    $_SESSION["user_id"] = $userId;
    $_SESSION["fullname"] = $user["fullname"];
    $_SESSION["email"] = $user["email"];

    echo json_encode([
        "success" => true,
        "message" => "Login successful",
        "user" => [
            "id" => $userId,
            "fullname" => $user["fullname"],
            "email" => $user["email"],
            "language" => $user["language"] ?? "English",
            "accent_color" => $user["accent_color"] ?? "blue",
            "created_at" => $user["created_at"]
        ],
        "settings" => [
            "strict_verification" => (bool)$user["strict_verification"],
            "auto_save_history" => (bool)$user["auto_save_history"],
            "notifications_enabled" => (bool)$user["notifications_enabled"]
        ]
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Something went wrong"
    ]);
}
?>
