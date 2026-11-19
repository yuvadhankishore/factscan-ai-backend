<?php
// Gemini AI Configuration and Service Provider
require_once __DIR__ . "/ai_keys.php";

class GeminiConfig {
    const DEFAULT_API_KEY = ""; 
    const MODELS = ["gemini-1.5-flash", "gemini-2.0-flash", "gemini-1.5-pro"];
    const API_BASE_URL = "https://generativelanguage.googleapis.com/v1beta/models/";

    public static function getApiKey(?string $userApiKey = null): string {
        $key = "";
        if (!empty($userApiKey) && trim($userApiKey) !== "") {
            $key = trim($userApiKey);
        } elseif (isset($_SERVER["HTTP_X_GEMINI_API_KEY"]) && trim($_SERVER["HTTP_X_GEMINI_API_KEY"]) !== "") {
            $key = trim($_SERVER["HTTP_X_GEMINI_API_KEY"]);
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $k => $v) {
                if (strcasecmp($k, 'X-Gemini-Api-Key') === 0 && !empty(trim($v))) {
                    $key = trim($v);
                    break;
                }
            }
        }

        if (empty($key)) {
            $envKey = getenv("GEMINI_API_KEY");
            if ($envKey !== false && trim($envKey) !== "") {
                $key = trim($envKey);
            } elseif (defined("FACTSCAN_GEMINI_API_KEY") && FACTSCAN_GEMINI_API_KEY !== "") {
                $key = FACTSCAN_GEMINI_API_KEY;
            }
        }

        // Clean and sanitize common copy-paste errors
        $key = preg_replace('/^GEMINI_API_KEY\s*=\s*/i', '', trim($key));
        $key = trim($key, " \t\n\r\0\x0B\"'");

        // Ignore placeholder text
        if (strpos($key, "your_") !== false || strpos($key, "AIzaSy...") !== false || strlen($key) < 20) {
            return "";
        }

        return $key;
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
                "maxOutputTokens" => 1500,
                "responseMimeType" => "application/json"
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
     * Multimodal Content Generation with Gemini 1.5 / 2.0 Flash
     * Supports image screenshots, document scans, infographics, and audio
     *
     * @param string $prompt Verification instruction prompt
     * @param string|null $base64Data Base64 encoded binary data (image/jpeg, image/png, application/pdf, audio/mp3)
     * @param string|null $mimeType MIME type of the uploaded media
     * @param string|null $apiKey Optional explicit API key
     * @return string|null Generated JSON response text
     */
    public static function generateMultimodalContent(
        string $prompt,
        ?string $base64Data = null,
        ?string $mimeType = null,
        ?string $apiKey = null
    ): ?string {
        $key = self::getApiKey($apiKey);
        if (empty($key)) {
            return null;
        }

        $parts = [
            ["text" => $prompt]
        ];

        if (!empty($base64Data)) {
            // Strip data URL prefixes if present (e.g. data:image/png;base64,...)
            $cleanBase64 = preg_replace('/^data:[^;]+;base64,/i', '', $base64Data);
            $cleanMime = !empty($mimeType) ? strtolower(trim($mimeType)) : "image/jpeg";

            $parts[] = [
                "inlineData" => [
                    "mimeType" => $cleanMime,
                    "data" => $cleanBase64
                ]
            ];
        }

        $payload = [
            "contents" => [
                [
                    "parts" => $parts
                ]
            ],
            "generationConfig" => [
                "temperature" => 0.1,
                "topP" => 0.8,
                "maxOutputTokens" => 2048,
                "responseMimeType" => "application/json"
            ]
        ];
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
            curl_setopt($ch, CURLOPT_TIMEOUT, 18);
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
                error_log("Gemini Multimodal API ($modelName) failed (HTTP $httpCode): " . ($error ?: $response));
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

    public static function generateMultiSourceContent(string $claim, array $crossSources, ?string $apiKey = null): ?string {
        $sourceText = "";
        if (!empty($crossSources)) {
            $sourceText = "\n\nCross-Referenced Live News Articles:\n";
            foreach ($crossSources as $i => $art) {
                $idx = $i + 1;
                $t = $art["title"] ?? "";
                $p = $art["publisher"] ?? "Wire Service";
                $u = $art["url"] ?? "";
                $s = $art["snippet"] ?? "";
                $sourceText .= "$idx. [$p] $t ($u)\n   Snippet: $s\n";
            }
        }

        $fullPrompt = <<<PROMPT
You are FactScan AI, an expert multi-source news and factual truth verification agent.
Your objective is to strictly analyze the claim against verified world geography, political science, history, scientific consensus, and the retrieved live news sources.

IMPORTANT FACT-CHECKING RULES:
1. GEOPOLITICAL & ENTITY TRUTH: Strictly audit if sovereign entities, geographic classifications, or titles are misstated (e.g., claiming "India is a state", "Paris is a country", or "Africa is a country" must be classified as FAKE with 98%+ confidence, explaining that India is a sovereign nation containing 28 states and 8 union territories, not a state).
2. FACTUAL VERACITY: If the assertion is factually incorrect, misleading, or contradicted by established global records, set "isReal": false and "verdict": "FAKE". Only set "isReal": true if the proposition is genuinely true.
3. OUTPUT FORMAT: Return ONLY pure, parseable JSON with no conversational preamble:
{
  "verdict": "REAL" or "FAKE",
  "isReal": true or false,
  "confidence": 90,
  "headline": "Clear descriptive title of the fact check",
  "summary": "Accurate 2-3 sentence explanation with verifiable real-world facts",
  "keyFindings": ["Key fact 1", "Key fact 2", "Key fact 3"],
  "warningSignals": ["Factual error or consistency signal"],
  "verifiedSources": ["Official institution 1", "Accredited registry 2"]
}

Claim to analyze:
"$claim"
$sourceText
PROMPT;
        return self::generateContent($fullPrompt, $apiKey);
    }
}
?>