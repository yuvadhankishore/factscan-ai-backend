<?php
// FactScan AI - Unified Multi-Agent AI Verification Orchestrator
// Coordinates Google Gemini & OpenAI ChatGPT Agents with Fallback to Trained Neural Models

require_once __DIR__ . "/gemini.php";
require_once __DIR__ . "/openai.php";
require_once __DIR__ . "/ai_keys.php";
require_once __DIR__ . "/../ml/factcheck_engine.php";

class AiAgentOrchestrator {

    /**
     * Verify news claim, URL article, or document content using Gemini or ChatGPT Agent
     */
    public static function verifyClaim(
        string $text,
        string $type = "TEXT_CLAIM",
        ?array $parsedArticle = null,
        ?string $geminiKey = null,
        ?string $openaiKey = null
    ): array {
        $activeGeminiKey = GeminiConfig::getApiKey($geminiKey);
        $activeOpenAiKey = OpenAiConfig::getApiKey($openaiKey);

        $prompt = self::buildVerificationPrompt($text, $type, $parsedArticle);

        // 1. Priority 1: OpenAI ChatGPT Agent (if key is configured)
        if (!empty($activeOpenAiKey)) {
            $chatMessages = [
                [
                    "role" => "system",
                    "content" => "You are FactScan AI, an expert international news verification and fake news detection agent. Your job is to analyze news claims, articles, and URLs and determine with high accuracy whether they are 'REAL', 'FAKE', or 'MISLEADING'. Always return pure JSON matching the requested schema."
                ],
                [
                    "role" => "user",
                    "content" => $prompt
                ]
            ];

            $openAiResponse = OpenAiConfig::generateChatCompletion($chatMessages, $activeOpenAiKey, "gpt-4o-mini");
            if ($openAiResponse) {
                $parsed = self::extractAndParseJson($openAiResponse);
                if ($parsed && isset($parsed["verdict"])) {
                    return self::formatAgentResult($parsed, "chatgpt_agent_gpt4o_mini", $parsedArticle, $text);
                }
            }
        }

        // 2. Priority 2: Google Gemini AI Agent (if key is configured)
        if (!empty($activeGeminiKey)) {
            $geminiResponse = GeminiConfig::generateContent($prompt, $activeGeminiKey);
            if ($geminiResponse) {
                $parsed = self::extractAndParseJson($geminiResponse);
                if ($parsed && isset($parsed["verdict"])) {
                    return self::formatAgentResult($parsed, "gemini_agent_flash", $parsedArticle, $text);
                }
            }
        }

        // 3. Priority 3: Local Knowledge-Assisted Ensemble Model & Rule Agent
        $textToAnalyze = ($type === "URL" && $parsedArticle)
            ? ($parsedArticle["title"] . " " . $parsedArticle["bodyText"])
            : $text;
        $sourceUrl = ($type === "URL") ? $text : null;

        $eval = FactCheckEngine::evaluate($textToAnalyze, $type, $sourceUrl);

        if ($type === "URL" && $parsedArticle) {
            $eval["headline"] = !empty($parsedArticle["title"]) ? $parsedArticle["title"] : $eval["headline"];
            $eval["publisher"] = !empty($parsedArticle["publisher"]) ? $parsedArticle["publisher"] : $eval["publisher"];
        }

        return $eval;
    }

    private static function buildVerificationPrompt(string $text, string $type, ?array $parsedArticle): string {
        if ($type === "URL" && $parsedArticle) {
            return <<<EOT
You are FactScan AI, an expert international news verification agent.
Analyze the provided webpage article content from this URL:

Article URL: {$parsedArticle['url']}
Article Title: {$parsedArticle['title']}
Publisher / Domain: {$parsedArticle['publisher']}
Article Body:
{$parsedArticle['bodyText']}

Instructions:
1. Determine whether this article is 'REAL', 'FAKE', or 'MISLEADING'.
   - REAL: Corroborated factual reporting, accredited announcements (NASA, WHO, Reuters, BBC, government departments, peer-reviewed journals).
   - FAKE: Fabricated claims, viral hoaxes, unscientific myths, scams, or known satirical articles.
   - MISLEADING: Clickbait sensationalism that distorts the actual underlying facts or omits crucial context.
2. Formulate a 2-line evidence-backed summary explaining WHY it is real, fake, or misleading.
3. Provide 3 specific key findings and 2 warning or credibility signals.

Return ONLY a clean JSON object (no markdown fences, no backticks, no extra text):
{
  "verdict": "REAL",
  "isReal": true,
  "confidence": 94,
  "headline": "{$parsedArticle['title']}",
  "publisher": "{$parsedArticle['publisher']}",
  "summary": "Evidence-backed summary explaining why this is true, false, or misleading.",
  "keyFindings": [
    "First specific factual finding explaining verification or debunking",
    "Second finding detailing source credibility and wire records",
    "Third finding on empirical consensus"
  ],
  "warningSignals": [
    "Credibility or warning signal 1",
    "Credibility or warning signal 2"
  ],
  "verifiedSources": [
    {
      "title": "Reuters Fact Check / Official Registry",
      "url": "https://www.reuters.com/fact-check",
      "description": "Corroborated by independent news archives"
    }
  ]
}
EOT;
        } else {
            return <<<EOT
You are FactScan AI, an expert international news verification and fake news detection agent.
Analyze the following news claim or submitted content:
"$text"

Instructions:
1. Determine whether this claim is 'REAL', 'FAKE', or 'MISLEADING':
   - FAKE: Fabricated hoaxes (e.g. Earth going dark for days, lemon water cures cancer, garlic cures heart attack, free 50k money distribution, 2000 rupee note GPS chip, microchips in vaccines, death hoaxes).
   - MISLEADING: Clickbait, exaggerated statistics, partial truths taken out of context, sensationalized claims.
   - REAL: Verified news, accredited announcements (NASA, WHO, ISRO, RBI, government gazettes, peer-reviewed studies).
2. Formulate a concise 1-line headline describing the claim.
3. Identify the publisher or source (e.g. 'WHO Press Wire', 'NASA Science', or 'Unverified Viral Claim').
4. Formulate a 2-line evidence-backed summary explaining the factual basis.
5. Provide 3 specific key findings and 2 warning or credibility signals.

Return ONLY a clean JSON object (no markdown fences, no backticks, no extra text):
{
  "verdict": "REAL",
  "isReal": true,
  "confidence": 95,
  "headline": "Concise 1-line headline describing the claim",
  "publisher": "Identified source or 'Unverified Viral Claim'",
  "summary": "2-line evidence-backed explanation of why this is true, false, or misleading.",
  "keyFindings": [
    "First specific factual finding explaining verification or debunking",
    "Second finding detailing empirical corroboration or lack thereof",
    "Third finding detailing source credibility"
  ],
  "warningSignals": [
    "Credibility or warning signal 1",
    "Credibility or warning signal 2"
  ],
  "verifiedSources": [
    {
      "title": "Reuters Fact Check",
      "url": "https://www.reuters.com/fact-check",
      "description": "Independent global news investigation record"
    }
  ]
}
EOT;
        }
    }

    private static function extractAndParseJson(string $raw): ?array {
        $jsonStr = "";
        if (preg_match('/\{[\s\S]*\}/', $raw, $matches)) {
            $jsonStr = $matches[0];
        } else {
            $jsonStr = trim($raw);
        }

        $parsed = json_decode($jsonStr, true);
        return is_array($parsed) ? $parsed : null;
    }

    private static function formatAgentResult(array $parsed, string $engineName, ?array $parsedArticle, string $originalText): array {
        $verdict = strtoupper(trim($parsed["verdict"] ?? ""));
        if (!in_array($verdict, ["REAL", "FAKE", "MISLEADING"])) {
            $verdict = !empty($parsed["isReal"]) ? "REAL" : "FAKE";
        }
        $isReal = ($verdict === "REAL");

        $confidence = (int)($parsed["confidence"] ?? 92);
        if ($confidence < 50 || $confidence > 99) {
            $confidence = 92;
        }

        $headline = (string)($parsed["headline"] ?? ($parsedArticle ? $parsedArticle["title"] : substr($originalText, 0, 75)));
        $publisher = (string)($parsed["publisher"] ?? ($parsedArticle ? $parsedArticle["publisher"] : ($isReal ? "AI Verified Source" : "Unverified Viral Claim")));

        $summary = (string)($parsed["summary"] ?? "Analysis corroborated by independent verification records.");
        $keyFindings = is_array($parsed["keyFindings"] ?? null) ? $parsed["keyFindings"] : [];
        $warningSignals = is_array($parsed["warningSignals"] ?? null) ? $parsed["warningSignals"] : [];
        if (empty($keyFindings) && !empty($warningSignals)) {
            $keyFindings = $warningSignals;
        }

        return [
            "headline" => $headline,
            "publisher" => $publisher,
            "verdict" => $verdict,
            "isReal" => $isReal,
            "confidence" => $confidence,
            "summary" => $summary,
            "keyFindings" => $keyFindings,
            "warningSignals" => $warningSignals,
            "verifiedSources" => is_array($parsed["verifiedSources"] ?? null) ? $parsed["verifiedSources"] : [],
            "engine" => $engineName
        ];
    }
}
?>

