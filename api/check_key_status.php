<?php
/**
 * FactScan AI - API Key & Model Health Diagnostic Endpoint
 * Endpoint: GET /api/check_key_status.php
 */

@header("Access-Control-Allow-Origin: *");
@header("Access-Control-Allow-Methods: GET, OPTIONS");
@header("Content-Type: application/json; charset=UTF-8");

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once __DIR__ . "/../config/ai_keys.php";
require_once __DIR__ . "/../config/gemini.php";

$allKeys = AIKeysConfig::getAllGeminiKeys();
$currentKey = AIKeysConfig::getGeminiKey();

$keyStatus = [];
foreach ($allKeys as $idx => $key) {
    $masked = substr($key, 0, 6) . "..." . substr($key, -4);
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key=" . urlencode($key);
    
    $payload = [
        "contents" => [
            ["role" => "user", "parts" => [["text" => "Ping. Respond 'PONG'."]]]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $keyStatus[] = [
        "index" => $idx,
        "key_masked" => $masked,
        "is_active" => ($code === 200),
        "http_code" => $code,
        "error" => $err ?: ($code === 200 ? null : "HTTP Error $code")
    ];
}

echo json_encode([
    "success" => true,
    "timestamp" => date("Y-m-d H:i:s T"),
    "keys_configured" => count($allKeys),
    "primary_key" => !empty($currentKey) ? substr($currentKey, 0, 6) . "..." . substr($currentKey, -4) : "NONE",
    "active_models" => GeminiConfig::MODELS,
    "details" => $keyStatus,
    "how_to_update" => "Add or replace GEMINI_API_KEY in factscanai_backend/.env file. Multiple keys can be separated by commas."
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
