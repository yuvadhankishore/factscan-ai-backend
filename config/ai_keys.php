<?php
// FactScan AI - Server-Side AI Verification Keys Configuration
// Server manages all AI verification keys privately; client apps never handle API keys.

if (!defined("FACTSCAN_GEMINI_API_KEY")) {
    $gKey = getenv("GEMINI_API_KEY") ?: "";
    $possibleEnvPaths = [
        dirname(__DIR__) . "/.env",
        __DIR__ . "/.env",
        "c:/xampp/htdocs/factscanai_backend/.env"
    ];
    foreach ($possibleEnvPaths as $envPath) {
        if (empty($gKey) && file_exists($envPath)) {
            $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, '#') === 0) continue;
                    if (preg_match('/^GEMINI_API_KEY\s*=\s*(.+)$/i', $line, $m)) {
                        $val = trim($m[1], " \t\n\r\0\x0B\"'");
                        $val = preg_replace('/^GEMINI_API_KEY\s*=\s*/i', '', $val);
                        if (!empty($val) && strlen($val) > 20 && strpos($val, "your_") === false) {
                            $gKey = $val;
                            break 2;
                        }
                    }
                }
            }
        }
    }
    if (empty($gKey) && file_exists(__DIR__ . "/ai_keys.json")) {
        $jsonKeys = @json_decode(file_get_contents(__DIR__ . "/ai_keys.json"), true);
        $gKey = $jsonKeys["GEMINI_API_KEY"] ?? "";
    }
    define("FACTSCAN_GEMINI_API_KEY", trim($gKey));
}

if (!defined("FACTSCAN_OPENAI_API_KEY")) {
    $oKey = getenv("OPENAI_API_KEY") ?: "";
    define("FACTSCAN_OPENAI_API_KEY", trim($oKey));
}
?>

