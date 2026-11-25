<?php
/**
 * FactScan AI - Live Hoax Radar Endpoint
 * Endpoint: GET /api/hoax_radar.php?category=All&limit=20
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/hoax_radar_feed.php";

$category = trim($_GET["category"] ?? "All");
$limit = isset($_GET["limit"]) ? min(50, max(1, (int)$_GET["limit"])) : 20;
$forceRefresh = !empty($_GET["refresh"]);

$cacheDir = dirname(__DIR__) . "/storage/cache";
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
$cacheFile = $cacheDir . "/hoax_radar_" . md5(strtolower($category) . "_" . $limit) . ".json";

if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile) < 600)) {
    $cached = @file_get_contents($cacheFile);
    if (!empty($cached)) {
        echo $cached;
        exit();
    }
}

$alerts = HoaxRadarFeed::getAlerts($conn ?? null, $category, $limit);

$payload = json_encode([
    "success" => true,
    "category" => $category,
    "count" => count($alerts),
    "unreadCount" => count($alerts),
    "alerts" => $alerts,
    "timestamp" => date("M d, Y • g:i A")
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

@file_put_contents($cacheFile, $payload);
echo $payload;
