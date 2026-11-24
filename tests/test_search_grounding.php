<?php
require_once __DIR__ . "/../config/ai_keys.php";
require_once __DIR__ . "/../config/gemini.php";

$key = GeminiConfig::getApiKey();

$models = ["gemini-3.5-flash-lite", "gemini-3.1-flash-lite", "gemini-flash-lite-latest"];

foreach ($models as $m) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key=" . urlencode($key);
    $payload = json_encode([
        "contents" => [
            [
                "role" => "user",
                "parts" => [
                    ["text" => "Fact check: 'Drinking boiled lemon water cures cancer in 48 hours'. Output strictly raw JSON with keys: verdict, confidenceScore, summary, reasoning, sources."]
                ]
            ]
        ]
    ]);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Model $m standard => HTTP $code\n";
    if ($code === 200) {
        echo "SUCCESSFUL REAL-TIME GEMINI GENERATION!\n$res\n";
        break;
    }
}
