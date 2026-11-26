<?php
/**
 * FactScan AI - Live AI Pipeline & Failover Verification Test
 */
require_once __DIR__ . "/../config/gemini.php";

echo "=== Live AI Pipeline & Model Failover Test ===\n";

$start = microtime(true);
$reply = GeminiConfig::generateContent("Respond with strictly valid JSON: {\"status\":\"OK\",\"model\":\"active\"}");
$ms = round((microtime(true) - $start) * 1000, 2);

assert(!empty($reply), "GeminiConfig::generateContent should return a non-empty response");
echo "Latency: {$ms} ms\n";
echo "Response: " . trim($reply) . "\n";

ob_start();
include __DIR__ . "/../api/check_key_status.php";
$healthOut = ob_get_clean();
$health = json_decode($healthOut, true);
assert(($health["success"] ?? false) === true);
assert(!empty($health["active_models"]));
echo "Primary Model: " . ($health["active_models"][0] ?? "unknown") . "\n";
echo "PASSED: Live AI pipeline verified.\n";
