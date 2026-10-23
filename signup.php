<?php
// signup_process.php
require_once 'config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// 1. Capture and sanitize inputs
$name             = trim($_POST['name'] ?? '');
$email            = trim($_POST['email'] ?? '');
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// 2. Input validation checks
if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
    die("Validation Error: All fields are required.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Validation Error: Please provide a valid email address.");
}

if (strlen($password) < 6) {
    die("Validation Error: Password must be at least 6 characters long.");
}

if ($password !== $confirm_password) {
    die("Validation Error: Password and Confirm Password do not match.");
}

try {
    // 3. Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);

    if ($stmt->fetch()) {
        die("Error: An account with this email already exists.");
    }

    // 4. Securely hash the password (BCRYPT)
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // 5. Insert new user into database
    $insertStmt = $pdo->prepare("
        INSERT INTO users (name, email, password) 
        VALUES (:name, :email, :password)
    ");
    
    $success = $insertStmt->execute([
        'name'     => $name,
        'email'    => $email,
        'password' => $hashedPassword,
    ]);

    if ($success) {
        // Successful registration
        echo "Registration successful! Welcome, " . htmlspecialchars($name) . ".";
        // To redirect to login: header('Location: login.php'); exit;
    } else {
        echo "Registration failed. Please try again.";
    }

} catch (PDOException $e) {
    echo "Database query error: " . $e->getMessage();
}