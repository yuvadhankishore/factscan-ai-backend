<?php
// Gemini AI Configuration and Service Provider
require_once __DIR__ . "/ai_keys.php";

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

        return defined("FACTSCAN_GEMINI_API_KEY") && FACTSCAN_GEMINI_API_KEY !== "" ? FACTSCAN_GEMINI_API_KEY : self::DEFAULT_API_KEY;
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

    /**
     * Multi-Turn Conversational Chat with System Persona (ChatGPT / Gemini style)
     *
     * @param array $messages Array of ['role' => 'user'|'model'|'assistant', 'content' => '...']
     * @param string|null $systemInstruction Custom instructions defining the AI's persona and knowledge
     * @param string|null $apiKey Optional explicit API key
     * @return string|null Generated response text
     */
    public static function chat(array $messages, ?string $systemInstruction = null, ?string $apiKey = null): ?string {
        $key = self::getApiKey($apiKey);
        if (empty($key) || empty($messages)) {
            return null;
        }

        $formattedContents = [];
        foreach ($messages as $m) {
            $role = (isset($m['role']) && ($m['role'] === 'model' || $m['role'] === 'assistant')) ? 'model' : 'user';
            $text = trim($m['content'] ?? $m['text'] ?? '');
            if ($text !== '') {
                $formattedContents[] = [
                    "role" => $role,
                    "parts" => [
                        ["text" => $text]
                    ]
                ];
            }
        }

        if (empty($formattedContents)) {
            return null;
        }

        $payload = [
            "contents" => $formattedContents,
            "generationConfig" => [
                "temperature" => 0.7,
                "topP" => 0.95,
                "maxOutputTokens" => 2048
            ]
        ];

        if (!empty($systemInstruction)) {
            $payload["systemInstruction"] = [
                "parts" => [
                    ["text" => $systemInstruction]
                ]
            ];
        }

        $jsonPayload = json_encode($payload);

        foreach (self::MODELS as $modelName) {
            $url = self::API_BASE_URL . $modelName . ":generateContent?key=" . urlencode($key);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
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
                error_log("Gemini Chat API ($modelName) failed (HTTP $httpCode): " . ($error ?: $response));
                if ($httpCode === 400 || $httpCode === 403) {
                    break;
                }
            }
        }

        return null;
    }
}
?>