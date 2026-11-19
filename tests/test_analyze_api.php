<?php
// Test runner for api/analyze.php
$_SERVER["REQUEST_METHOD"] = "POST";
$_POST["input"] = "Drinking boiled lemon water cures cancer in 48 hours";
$_POST["type"] = "TEXT";

ob_start();
include __DIR__ . "/../api/analyze.php";
$output = ob_get_clean();

echo "RESPONSE:\n" . $output . "\n";
$data = json_decode($output, true);
if (isset($data["verdict"]) && $data["verdict"] === "FAKE") {
    echo "\nTEST PASSED! Verdict is FAKE, confidence is " . $data["confidenceScore"] . "\n";
    exit(0);
} else {
    echo "\nTEST FAILED!\n";
    exit(1);
}
