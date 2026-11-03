<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/config/db.php";

if ($conn) {
    try {
        $stmt = $conn->query("SELECT id, headline, publisher, is_real, confidence, verified_sources, warning_signals, created_at FROM verification_history ORDER BY id DESC LIMIT 5");
        $rows = $stmt->fetchAll();
        echo json_encode([
            "status" => "CONNECTED",
            "total_fetched" => count($rows),
            "records" => $rows
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        echo json_encode([
            "status" => "QUERY_ERROR",
            "error" => $e->getMessage()
        ], JSON_PRETTY_PRINT);
    }
} else {
    echo json_encode([
        "status" => "NO_CONNECTION",
        "error" => "Database connection failed"
    ], JSON_PRETTY_PRINT);
}
?>

