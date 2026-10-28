<?php
// Gemini AI Configuration and Service Provider

class GeminiConfig {
    // Default server-side API Key (can be set via environment variable or defined here)
    const DEFAULT_API_KEY = ""; 
    const MODEL = "gemini-1.5-flash";
    const API_BASE_URL = "https://generativelanguage.googleapis.com/v1beta/models/";

    public static function getApiKey(?string $userApiKey = null): string {
        if (!empty($userApiKey) && trim($userApiKey) !== "") {
            return trim($userApiKey);
        }

        $envKey = getenv("GEMINI_API_KEY");
        if ($envKey !== false && trim($envKey) !== "") {
            return trim($envKey);
        }

        return self::DEFAULT_API_KEY;
    }

    public static function generateContent(string $prompt, ?string $apiKey = null): ?string {
        $key = self::getApiKey($apiKey);
        if (empty($key)) {
            return null;
        }

        $url = self::API_BASE_URL . self::MODEL . ":generateContent?key=" . urlencode($key);

        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => 0.2,
                "topP" => 0.8,
                "maxOutputTokens" => 1024
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200 || !$response) {
            error_log("Gemini API call failed (HTTP $httpCode): " . ($error ?: $response));
            return null;
        }

        $data = json_decode($response, true);
        $text = $data["candidates"][0]["content"]["parts"][0]["text"] ?? null;
        return $text;
    }
}
?>