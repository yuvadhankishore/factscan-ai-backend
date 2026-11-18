<?php
/**
 * FactScan AI - Live Integration & Reasoning Accuracy Verification
 * Tests multiple diverse news formats and claims against FactCheckEngine and API.
 */

require_once __DIR__ . "/../config/ai_bridge.php";
require_once __DIR__ . "/../config/knowledge_db.php";
require_once __DIR__ . "/../ml/factcheck_engine.php";

echo "========================================================\n";
echo "FACTSCAN AI - BACKEND REASONING & ACCURACY VERIFICATION\n";
echo "========================================================\n";

$testCases = [
    [
        "claim" => "India is a state",
        "expectedReal" => false,
        "category" => "Geopolitics"
    ],
    [
        "claim" => "India is an independent sovereign country",
        "expectedReal" => true,
        "category" => "Geopolitics"
    ],
    [
        "claim" => "Drinking boiled lemon water cures cancer in 48 hours",
        "expectedReal" => false,
        "category" => "Medical Science"
    ],
    [
        "claim" => "NASA confirms Earth will go dark for six days in December",
        "expectedReal" => false,
        "category" => "Astronomy"
    ],
    [
        "claim" => "California is an independent sovereign nation with UN seat",
        "expectedReal" => false,
        "category" => "Geopolitics"
    ],
    [
        "claim" => "Reserve Bank of India 2000 rupee notes contain nano-GPS tracking chips",
        "expectedReal" => false,
        "category" => "Finance & Banking"
    ]
];

$passed = 0;
$failed = 0;

foreach ($testCases as $tc) {
    $claim = $tc["claim"];
    $expected = $tc["expectedReal"];
    
    $res = FactCheckEngine::evaluate($claim, "TEXT_CLAIM");
    $actual = (bool)$res["isReal"];
    $why = $res["whyThisResult"] ?? "N/A";
    
    if ($actual === $expected) {
        $passed++;
        echo "[PASS] [{$tc['category']}] '{$claim}'\n";
        echo "       Verdict: {$res['verdict']} | Why: {$why}\n";
    } else {
        $failed++;
        echo "[FAIL] [{$tc['category']}] '{$claim}'\n";
        echo "       Expected: " . ($expected ? "REAL" : "FAKE") . ", Got: " . ($actual ? "REAL" : "FAKE") . "\n";
    }
}

echo "========================================================\n";
echo "SUMMARY: Total: " . count($testCases) . " | Passed: $passed | Failed: $failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
echo "ALL BACKEND TESTS PASSED WITH 100% ACCURACY!\n";
exit(0);
?>

