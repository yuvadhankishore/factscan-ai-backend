<?php
// OpenAI (ChatGPT) AI Agent Service Provider for FactScan AI

require_once __DIR__ . "/ai_keys.php";

class OpenAiConfig {
    const API_URL = "https://api.openai.com/v1/chat/completions";
    const MODELS = ["gpt-4o-mini", "gpt-4o", "gpt-3.5-turbo"];

    public static function getApiKey(?string $userApiKey = null): string {
        if (!empty($userApiKey) && trim($userApiKey) !== "") {
            return trim($userApiKey);
        }

        if (isset($_SERVER["HTTP_X_OPENAI_API_KEY"]) && trim($_SERVER["HTTP_X_OPENAI_API_KEY"]) !== "") {
            return trim($_SERVER["HTTP_X_OPENAI_API_KEY"]);
        }
        if (isset($_SERVER["HTTP_X_CHATGPT_API_KEY"]) && trim($_SERVER["HTTP_X_CHATGPT_API_KEY"]) !== "") {
            return trim($_SERVER["HTTP_X_CHATGPT_API_KEY"]);
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $k => $v) {
                if ((strcasecmp($k, 'X-OpenAI-Api-Key') === 0 || strcasecmp($k, 'X-ChatGPT-Api-Key') === 0) && !empty(trim($v))) {
                    return trim($v);
                }
            }
        }

        $envKey = getenv("OPENAI_API_KEY");
        if ($envKey !== false && trim($envKey) !== "") {
            return trim($envKey);
        }

        return defined("FACTSCAN_OPENAI_API_KEY") ? FACTSCAN_OPENAI_API_KEY : "";
    }

    public static function generateChatCompletion(array $messages, ?string $apiKey = null, string $model = "gpt-4o-mini"): ?string {
        $key = self::getApiKey($apiKey);
        if (empty($key)) {
            return null;
        }

        $payload = [
            "model" => $model,
            "messages" => $messages,
            "temperature" => 0.1,
            "max_tokens" => 1500
        ];

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer " . $key
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            return $data["choices"][0]["message"]["content"] ?? null;
        }

        error_log("OpenAI API error ($httpCode): " . ($curlErr ?: $response));
        return null;
    }
}
?>
