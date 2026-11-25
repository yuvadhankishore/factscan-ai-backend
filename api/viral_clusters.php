<?php
/**
 * FactScan AI - Viral Hoax Clusters Endpoint
 * Endpoint: GET /api/viral_clusters.php
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/viral_cluster_tracker.php";

$limit = isset($_GET["limit"]) ? min(50, max(1, (int)$_GET["limit"])) : 15;
$clusters = ViralClusterTracker::getTopViralClusters($conn ?? null, $limit);

echo json_encode([
    "success" => true,
    "count" => count($clusters),
    "clusters" => $clusters,
    "timestamp" => date("Y-m-d H:i:s T")
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
