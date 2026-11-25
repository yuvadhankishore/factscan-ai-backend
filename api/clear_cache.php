<?php
/**
 * FactScan AI - Non-Destructive Cache Cleanup Endpoint
 * POST /api/clear_cache.php
 * Clears temporary analysis/radar cache JSON files without touching user history or saved bookmarks.
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

$cacheDir = dirname(__DIR__) . "/storage/cache";
$cleared = 0;
$freedBytes = 0;

if (is_dir($cacheDir)) {
    foreach (glob($cacheDir . "/{analysis_,hoax_radar_,ms_}*.json", GLOB_BRACE) as $file) {
        $size = @filesize($file) ?: 0;
        if (@unlink($file)) {
            $cleared++;
            $freedBytes += $size;
        }
    }
}

echo json_encode([
    "success" => true,
    "clearedFiles" => $cleared,
    "freedKb" => round($freedBytes / 1024, 1),
    "message" => "Cleared $cleared temporary cache files."
]);
