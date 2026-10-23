<?php
// config.php

$host = 'localhost';
$dbname = 'factscan_ai'; // If your DB literally has a space, change to: 'factscan ai'
$username = 'root';       // Default XAMPP MySQL user
$password = '';           // Default XAMPP MySQL password is empty

try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}