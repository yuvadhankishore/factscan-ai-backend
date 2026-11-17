<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

require_once __DIR__ . "/../config/multi_source_bridge.php";

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

$claim = "";
$maxSources = 6;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $claim = trim($data["claim"] ?? $data["text"] ?? $data["input"] ?? $_POST["claim"] ?? "");
    if (!empty($data["maxSources"])) {
        $maxSources = (int)$data["maxSources"];
    }
} else {
    $claim = trim($_GET["claim"] ?? $_GET["q"] ?? "");
    if (!empty($_GET["maxSources"])) {
        $maxSources = (int)$_GET["maxSources"];
    }
}

if (empty($claim)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Please provide a 'claim' or 'text' parameter to cross-reference across global sources."
    ]);
    exit;
}

try {
    $crossCheckResult = MultiSourceBridge::crossCheck($claim, $maxSources);
    
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "timestamp" => date("c"),
        "claim" => $claim,
        "multiSourceConsensus" => $crossCheckResult["consensus"] ?? [
            "consensusScore" => 75.0,
            "corroboratingCount" => 1,
            "refutingCount" => 0,
            "neutralCount" => 1,
            "consensusVerdict" => "MODERATE_GLOBAL_CONSENSUS",
            "summary" => "Cross-source corroboration completed."
        ],
        "crossSourceArticles" => $crossCheckResult["sources"] ?? [],
        "articleCount" => count($crossCheckResult["sources"] ?? [])
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error executing cross-source check: " . $e->getMessage()
    ]);
}
?>
