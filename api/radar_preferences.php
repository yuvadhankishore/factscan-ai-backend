<?php
/**
 * FactScan AI - Granular Hoax Radar Alert Topic Preferences API
 * GET /api/radar_preferences.php
 * POST /api/radar_preferences.php { "topics": ["Health", "Finance"] }
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/profile_preferences_manager.php";

$userId = (int)($_SERVER["HTTP_X_USER_ID"] ?? $_GET["user_id"] ?? 1);
if ($userId < 1) $userId = 1;

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $topics = ProfilePreferencesManager::getRadarTopics($userId, $conn ?? null);
    echo json_encode([
        "success" => true,
        "topics" => $topics
    ]);
    exit();
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true) ?: $_POST;
$topics = is_array($data["topics"] ?? null) ? $data["topics"] : [];
$saved = ProfilePreferencesManager::saveRadarTopics($userId, $topics, $conn ?? null);

echo json_encode([
    "success" => true,
    "topics" => $saved
]);

