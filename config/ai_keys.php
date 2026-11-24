<?php
// FactScan AI - Server-Side AI Verification Keys Configuration
// Manages API key pooling, multi-key failover, and rotation for continuous 24/7 fact-checking.

class AIKeysConfig {
    private static array $geminiKeyPool = [];
    private static int $currentKeyIndex = 0;
    private static bool $initialized = false;

    public static function init(): void {
        if (self::$initialized) return;
        self::$initialized = true;

        $keysFound = [];

        // 1. Check environment variables
        $envKey = getenv("GEMINI_API_KEY") ?: getenv("GEMINI_API_KEYS") ?: "";
        if (!empty($envKey)) {
            $keysFound = array_merge($keysFound, explode(",", $envKey));
        }

        // 2. Check .env files
        $possibleEnvPaths = [
            dirname(__DIR__) . "/.env",
            __DIR__ . "/.env",
            "c:/xampp/htdocs/factscanai_backend/.env"
        ];

        foreach ($possibleEnvPaths as $envPath) {
            if (file_exists($envPath)) {
                $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines) {
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line) || strpos($line, '#') === 0) continue;
                        if (preg_match('/^GEMINI_API_KEY(?:S)?\s*=\s*(.+)$/i', $line, $m)) {
                            $rawVals = explode(",", $m[1]);
                            foreach ($rawVals as $v) {
                                $clean = trim($v, " \t\n\r\0\x0B\"'");
                                $clean = preg_replace('/^GEMINI_API_KEY(?:S)?\s*=\s*/i', '', $clean);
                                if (!empty($clean) && strlen($clean) >= 20 && strpos($clean, "your_") === false) {
                                    $keysFound[] = $clean;
                                }
                            }
                        }
                    }
                }
            }
        }

        // 3. Fallback to ai_keys.json
        $jsonPath = __DIR__ . "/ai_keys.json";
        if (file_exists($jsonPath)) {
            $json = @json_decode(file_get_contents($jsonPath), true);
            if (!empty($json["GEMINI_API_KEY"])) {
                $keysFound[] = trim($json["GEMINI_API_KEY"]);
            }
            if (!empty($json["GEMINI_API_KEYS"]) && is_array($json["GEMINI_API_KEYS"])) {
                $keysFound = array_merge($keysFound, $json["GEMINI_API_KEYS"]);
            }
        }

        // Deduplicate and filter keys
        self::$geminiKeyPool = array_values(array_unique(array_filter($keysFound, function($k) {
            return strlen($k) >= 20 && strpos($k, "your_") === false;
        })));

        if (!defined("FACTSCAN_GEMINI_API_KEY")) {
            define("FACTSCAN_GEMINI_API_KEY", self::$geminiKeyPool[0] ?? "");
        }
    }

    public static function getGeminiKey(): string {
        self::init();
        if (empty(self::$geminiKeyPool)) return "";
        return self::$geminiKeyPool[self::$currentKeyIndex % count(self::$geminiKeyPool)];
    }

    public static function getAllGeminiKeys(): array {
        self::init();
        return self::$geminiKeyPool;
    }

    public static function rotateToNextKey(): string {
        self::init();
        if (empty(self::$geminiKeyPool)) return "";
        self::$currentKeyIndex = (self::$currentKeyIndex + 1) % count(self::$geminiKeyPool);
        return self::getGeminiKey();
    }
}

AIKeysConfig::init();
