
<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

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

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    $data = $_POST;
}

$fullname = trim($data["fullname"] ?? "");
$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";

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
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO users (fullname, email, password)
         VALUES (:fullname, :email, :password)"
    );

    $stmt->execute([
        ":fullname" => $fullname,
        ":email" => $email,
        ":password" => $hashedPassword
    ]);

    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Signup successful"
    ]);

} catch (PDOException $e) {
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
            "message" => "Something went wrong"
        ]);
    }
}
?>
