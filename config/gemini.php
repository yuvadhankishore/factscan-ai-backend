<?php
// Gemini AI Configuration and Service Provider

class GeminiConfig {
    const DEFAULT_API_KEY = ""; 
    const MODELS = ["gemini-1.5-flash", "gemini-2.0-flash", "gemini-1.5-pro"];
    const API_BASE_URL = "https://generativelanguage.googleapis.com/v1beta/models/";

    public static function getApiKey(?string $userApiKey = null): string {
        if (!empty($userApiKey) && trim($userApiKey) !== "") {
            return trim($userApiKey);
        }

        // Check incoming request headers
        if (isset($_SERVER["HTTP_X_GEMINI_API_KEY"]) && trim($_SERVER["HTTP_X_GEMINI_API_KEY"]) !== "") {
            return trim($_SERVER["HTTP_X_GEMINI_API_KEY"]);
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $k => $v) {
                if (strcasecmp($k, 'X-Gemini-Api-Key') === 0 && !empty(trim($v))) {
                    return trim($v);
                }
            }
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

        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => 0.1,
                "topP" => 0.8,
                "maxOutputTokens" => 1500
            ]
        ];
        $jsonPayload = json_encode($payload);

        // Try primary model first, fallback to alternate Gemini models if model name varies
        foreach (self::MODELS as $modelName) {
            $url = self::API_BASE_URL . $modelName . ":generateContent?key=" . urlencode($key);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $text = $data["candidates"][0]["content"]["parts"][0]["text"] ?? null;
                if (!empty($text)) {
                    return $text;
                }
            } else {
                error_log("Gemini API ($modelName) failed (HTTP $httpCode): " . ($error ?: $response));
                // If invalid key or quota, trying next model might not help, but if 404 (model not found), try next
                if ($httpCode === 400 || $httpCode === 403) {
                    break;
                }
            }
        }

        return null;
    }
}
?>