<?php
/**
 * FactScan AI - Knowledge Search API
 * Allows fast retrieval of verified world facts, country profiles, and debunk rationales.
 */

ini_set('display_errors', '0');
error_reporting(0);
if (ob_get_level() === 0) {
    ob_start();
}

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

require_once __DIR__ . "/../config/knowledge_db.php";

$query = trim($_GET["q"] ?? $_GET["query"] ?? "");
if (empty($query) && $_SERVER["REQUEST_METHOD"] === "POST") {
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true) ?: [];
    $query = trim($data["query"] ?? $data["q"] ?? $data["text"] ?? "");
}

if (empty($query)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Parameter 'q' or 'query' is required"]);
    exit;
}

$fact = KnowledgeDatabase::lookupEntity($query);

if ($fact) {
    echo json_encode([
        "success" => true,
        "query" => $query,
        "found" => true,
        "data" => $fact
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        "success" => true,
        "query" => $query,
        "found" => false,
        "message" => "No specific knowledge base contradiction or entity found for query."
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
?>

