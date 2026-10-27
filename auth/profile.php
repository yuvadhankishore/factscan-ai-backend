
<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/../config/db.php";
function sendResponse($status, $data) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Requires your login API to set this session value.
$userId = filter_var(
    $_SESSION["user_id"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$userId || $userId < 1) {
    sendResponse(401, [
        "success" => false,
        "message" => "Please log in first"
    ]);
}

$method = $_SERVER["REQUEST_METHOD"];

try {
    if ($method === "GET") {
        $stmt = $conn->prepare(
            "SELECT id, fullname, email, created_at
             FROM users
             WHERE id = :id"
        );

        $stmt->execute(["id" => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            sendResponse(404, [
                "success" => false,
                "message" => "User not found"
            ]);
        }

        sendResponse(200, [
            "success" => true,
            "profile" => $user
        ]);
    }

    if ($method === "PUT") {
        $input = json_decode(
            file_get_contents("php://input"),
            true
        );

        if (!is_array($input)) {
            sendResponse(400, [
                "success" => false,
                "message" => "Invalid JSON"
            ]);
        }

        $fullname = trim($input["fullname"] ?? "");
        $email = trim($input["email"] ?? "");

        if (
            $fullname === "" ||
            strlen($fullname) > 100
        ) {
            sendResponse(422, [
                "success" => false,
                "message" => "Enter a valid full name"
            ]);
        }

        if (
            strlen($email) > 255 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            sendResponse(422, [
                "success" => false,
                "message" => "Enter a valid email"
            ]);
        }

        $stmt = $conn->prepare(
            "UPDATE users
             SET fullname = :fullname, email = :email
             WHERE id = :id"
        );

        $stmt->execute([
            "fullname" => $fullname,
            "email" => $email,
            "id" => $userId
        ]);

        sendResponse(200, [
            "success" => true,
            "message" => "Profile updated successfully"
        ]);
    }

    header("Allow: GET, PUT");

    sendResponse(405, [
        "success" => false,
        "message" => "Method not allowed"
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());

    if ($e->getCode() === "23000") {
        sendResponse(409, [
            "success" => false,
            "message" => "This email may already be registered"
        ]);
    }

    sendResponse(500, [
        "success" => false,
        "message" => "Database operation failed"
    ]);
}