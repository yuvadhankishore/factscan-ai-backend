<?php
// FactScan AI - AI Agent Keys Configuration
// You can provide your Google Gemini API Key and OpenAI (ChatGPT) API Key here or in environment variables.

if (!defined("FACTSCAN_GEMINI_API_KEY")) {
    $gKey = getenv("GEMINI_API_KEY") ?: "";
    if (empty($gKey) && file_exists(__DIR__ . "/.env")) {
        $env = parse_ini_file(__DIR__ . "/.env");
        $gKey = $env["GEMINI_API_KEY"] ?? "";
    }
    define("FACTSCAN_GEMINI_API_KEY", $gKey);
}

if (!defined("FACTSCAN_OPENAI_API_KEY")) {
    $oKey = getenv("OPENAI_API_KEY") ?: "";
    if (empty($oKey) && file_exists(__DIR__ . "/.env")) {
        $env = parse_ini_file(__DIR__ . "/.env");
        $oKey = $env["OPENAI_API_KEY"] ?? "";
    }
    define("FACTSCAN_OPENAI_API_KEY", $oKey);
}
?>

