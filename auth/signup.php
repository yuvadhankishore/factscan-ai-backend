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

$fullname = trim($data["fullname"] ?? $data["full_name"] ?? "");
$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";
$language = trim($data["language"] ?? "English");
$accentColor = trim($data["accent_color"] ?? "blue");

if ($fullname === "" || $email === "" || $password === "") {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Full name, email, and password are required"
    ]);
    exit;
}

if (strlen($fullname) > 100) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Full name must not exceed 100 characters"
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Please enter a valid email address"
    ]);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Password must be at least 8 characters"
    ]);
    exit;
}

try {
    $conn->beginTransaction();

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmtUser = $conn->prepare(
        "INSERT INTO users (fullname, email, password)
         VALUES (:fullname, :email, :password)"
    );
    $stmtUser->execute([
        ":fullname" => $fullname,
        ":email" => $email,
        ":password" => $hashedPassword
    ]);

    $userId = (int)$conn->lastInsertId();

    $stmtProfile = $conn->prepare(
        "INSERT INTO user_profiles (user_id, full_name, email, language, accent_color)
         VALUES (:user_id, :full_name, :email, :language, :accent_color)
         ON DUPLICATE KEY UPDATE 
            full_name = VALUES(full_name),
            email = VALUES(email),
            language = VALUES(language),
            accent_color = VALUES(accent_color)"
    );
    $stmtProfile->execute([
        ":user_id" => $userId,
        ":full_name" => $fullname,
        ":email" => $email,
        ":language" => $language ?: "English",
        ":accent_color" => $accentColor ?: "blue"
    ]);

    $stmtSettings = $conn->prepare(
        "INSERT INTO user_settings (user_id, strict_verification, auto_save_history, notifications_enabled)
         VALUES (:user_id, 1, 1, 1)
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)"
    );
    $stmtSettings->execute([":user_id" => $userId]);

    $conn->commit();

    $_SESSION["user_id"] = $userId;
    $_SESSION["fullname"] = $fullname;
    $_SESSION["email"] = $email;

    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Signup successful",
        "user" => [
            "id" => $userId,
            "fullname" => $fullname,
            "email" => $email,
            "language" => $language ?: "English",
            "accent_color" => $accentColor ?: "blue"
        ]
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    if ($e->getCode() === "23000") {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Email already registered"
        ]);
    } else {
        error_log($e->getMessage());
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Something went wrong: " . $e->getMessage()
        ]);
    }
}
?>
