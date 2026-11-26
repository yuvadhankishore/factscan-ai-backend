<?php
/**
 * FactScan AI - Viral Cluster Tracker Test Suite
 */
require_once __DIR__ . "/../config/viral_cluster_tracker.php";

echo "=== ViralClusterTracker Unit & Integration Test ===\n";

$claim1 = "Drinking hot lemon water cures stage 4 cancer overnight";
$claim2 = "Hot lemon water completely cures stage 4 cancer overnight!";

$t1 = ViralClusterTracker::tokenizeClaim($claim1);
$t2 = ViralClusterTracker::tokenizeClaim($claim2);
$sim = ViralClusterTracker::computeSimilarity($t1, $t2);

echo "Tokens 1: " . implode(", ", $t1) . "\n";
echo "Tokens 2: " . implode(", ", $t2) . "\n";
echo "Similarity: " . round($sim * 100, 1) . "%\n";

assert($sim >= 0.75, "Near-identical claims should have high Jaccard similarity");

$res1 = ViralClusterTracker::trackClaimSpread($claim1, "FAKE", null);
$res2 = ViralClusterTracker::trackClaimSpread($claim2, "FAKE", null);

echo "Run 1 Spread Count: " . $res1["viralSpreadCount"] . "\n";
echo "Run 2 Spread Count: " . $res2["viralSpreadCount"] . " | Warning: " . $res2["clusterWarning"] . "\n";

assert($res2["viralSpreadCount"] >= 2, "Similar claim should increment cluster spread count");
echo "PASSED: ViralClusterTracker verified.\n";

