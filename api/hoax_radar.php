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

$alerts = HoaxRadarFeed::getAlerts($conn ?? null, $category, $limit);

echo json_encode([
    "success" => true,
    "category" => $category,
    "count" => count($alerts),
    "unreadCount" => count($alerts),
    "alerts" => $alerts,
    "timestamp" => date("M d, Y • g:i A")
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
