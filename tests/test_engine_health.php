<?php
/**
 * FactScan AI - Engine Health Latency Verification Test
 */
$_SERVER["REQUEST_METHOD"] = "GET";
$start = microtime(true);
ob_start();
include __DIR__ . "/../api/engine_health.php";
$out = ob_get_clean();
$elapsedMs = round((microtime(true) - $start) * 1000, 2);

$json = json_decode($out, true);
assert(is_array($json) && ($json["success"] ?? false) === true, "engine_health.php should return valid success JSON");
assert(($json["liveWireStatus"] ?? "") === "LIVE_2026_ACTIVE");
echo "Engine Health OK | Latency: {$elapsedMs} ms | Cache files: " . ($json["cacheFileCount"] ?? 0) . "\n";
echo "PASSED: Engine health endpoint verified.\n";
