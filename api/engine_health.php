<?php
/**
 * FactScan AI - Live Engine Health, Cache Metrics & DB Latency Endpoint
 * GET /api/engine_health.php
 */

@header("Access-Control-Allow-Origin: *");
@header("Access-Control-Allow-Methods: GET, OPTIONS");
@header("Content-Type: application/json; charset=UTF-8");

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "OPTIONS") {
    http_response_code(200);
    exit();
}

$start = microtime(true);
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/ai_keys.php";

$dbOnline = false;
if ($conn instanceof PDO) {
    try {
        $conn->query("SELECT 1");
        $dbOnline = true;
    } catch (Throwable $e) {
        $dbOnline = false;
    }
}

$cacheDir = dirname(__DIR__) . "/storage/cache";
$cacheFiles = 0;
$cacheBytes = 0;
if (is_dir($cacheDir)) {
    foreach (glob($cacheDir . "/*.json") as $f) {
        $cacheFiles++;
        $cacheBytes += @filesize($f) ?: 0;
    }
}

$latencyMs = round((microtime(true) - $start) * 1000, 2);

echo json_encode([
    "success" => true,
    "status" => "ONLINE",
    "liveWireStatus" => "LIVE_2026_ACTIVE",
    "dbConnected" => $dbOnline,
    "apiKeysConfigured" => count(AIKeysConfig::getAllGeminiKeys()),
    "cacheFileCount" => $cacheFiles,
    "cacheSizeKb" => round($cacheBytes / 1024, 1),
    "latencyMs" => $latencyMs,
    "timestamp" => date("M d, Y • g:i A")
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
