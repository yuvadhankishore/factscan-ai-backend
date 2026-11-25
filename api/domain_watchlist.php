<?php
/**
 * FactScan AI - User Domain Watchlist API
 * GET /api/domain_watchlist.php
 * POST /api/domain_watchlist.php { "domain": "example.com", "rule_type": "TRUSTED"|"FLAGGED", "action": "add"|"remove" }
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
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
    $batchParam = trim($_GET["batch"] ?? "");
    if (!empty($batchParam)) {
        $domains = array_filter(array_map('trim', explode(',', $batchParam)));
        $results = [];
        foreach ($domains as $d) {
            $norm = ProfilePreferencesManager::normalizeDomain($d);
            $results[$norm] = ProfilePreferencesManager::checkDomainStatus($userId, $norm, $conn ?? null);
        }
        echo json_encode(["success" => true, "results" => $results]);
        exit();
    }

    $checkDomain = trim($_GET["check"] ?? "");
    if (!empty($checkDomain)) {
        $status = ProfilePreferencesManager::checkDomainStatus($userId, $checkDomain, $conn ?? null);
        echo json_encode([
            "success" => true,
            "domain" => ProfilePreferencesManager::normalizeDomain($checkDomain),
            "status" => $status
        ]);
        exit();
    }

    $list = ProfilePreferencesManager::getDomainWatchlist($userId, $conn ?? null);
    echo json_encode([
        "success" => true,
        "trusted" => $list["trusted"],
        "flagged" => $list["flagged"]
    ]);
    exit();
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true) ?: $_POST;
$action = strtolower(trim($data["action"] ?? "add"));
$domain = trim($data["domain"] ?? "");
$ruleType = strtoupper(trim($data["rule_type"] ?? "TRUSTED"));

if ($action === "remove" || $_SERVER["REQUEST_METHOD"] === "DELETE") {
    $res = ProfilePreferencesManager::removeDomainRule($userId, $domain, $conn ?? null);
    echo json_encode($res);
    exit();
}

$res = ProfilePreferencesManager::upsertDomainRule($userId, $domain, $ruleType, $conn ?? null);
echo json_encode($res);
