<?php
$_SERVER["REQUEST_METHOD"] = "POST";
$_POST["input"] = "India is an independent sovereign country";
$_POST["type"] = "TEXT";

ob_start();
include __DIR__ . "/../api/analyze.php";
$output = ob_get_clean();

echo "RESPONSE:\n" . $output . "\n";
$data = json_decode($output, true);
if (isset($data["verdict"]) && $data["verdict"] === "REAL") {
    echo "\nTEST PASSED! Sovereign country verdict is REAL, confidence is " . $data["confidenceScore"] . "\n";
    exit(0);
} else {
    echo "\nTEST FAILED!\n";
    exit(1);
}
