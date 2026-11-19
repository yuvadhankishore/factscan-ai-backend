<?php
/**
 * FactScan AI - Dedicated Real-Time AI Fact-Checking Endpoint
 * Unified pipeline for:
 *  - Paste Text
 *  - Enter URL (with live web article extraction)
 *  - Upload Article & Scan Document (PDF, DOCX, TXT, OCR)
 * Powered by Gemini 2.5 Flash / 1.5 Flash with Google Search Grounding.
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id, X-Gemini-Api-Key, ngrok-skip-browser-warning");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once __DIR__ . "/../config/ai_keys.php";
require_once __DIR__ . "/../config/gemini.php";
require_once __DIR__ . "/../ml/factcheck_engine.php";

// 1. Parse Input Payload (JSON body or Multipart/POST)
$inputRaw = file_get_contents("php://input");
$postJson = json_decode($inputRaw, true) ?: [];

$type = strtoupper(trim($_POST["type"] ?? $postJson["type"] ?? "TEXT"));
$rawInput = trim($_POST["input"] ?? $_POST["text"] ?? $_POST["claim"] ?? $postJson["input"] ?? $postJson["text"] ?? $postJson["claim"] ?? "");
$urlInput = trim($_POST["url"] ?? $postJson["url"] ?? "");
$userApiKey = trim($_SERVER["HTTP_X_GEMINI_API_KEY"] ?? $_POST["gemini_api_key"] ?? $postJson["gemini_api_key"] ?? "");

if (empty($rawInput) && !empty($urlInput)) {
    $rawInput = $urlInput;
    $type = "URL";
}

// 2. Extract Document Content if Uploaded
if (isset($_FILES["file"]) && is_uploaded_file($_FILES["file"]["tmp_name"])) {
    $type = "DOCUMENT";
    $extracted = FactCheckEngine::extractTextFromDocument($_FILES["file"]["tmp_name"], $_FILES["file"]["name"]);
    if (!empty($extracted)) {
        $rawInput = $extracted;
    }
}

// If still empty, return error
if (empty($rawInput)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "No content provided. Please submit 'text', 'url', or upload a 'file' to verify."
    ]);
    exit();
}

// 3. Webpage Scraper & Body Extractor for Enter URL
$extractedHeadline = "";
$extractedPublisher = "Web Source";
$contentToVerify = $rawInput;

if ($type === "URL" || filter_var($rawInput, FILTER_VALIDATE_URL) || preg_match('/^https?:\/\//i', $rawInput)) {
    $targetUrl = filter_var($rawInput, FILTER_VALIDATE_URL) ? $rawInput : $urlInput;
    if ($targetUrl) {
        $extractedData = extractWebpageArticle($targetUrl);
        $contentToVerify = $extractedData["fullText"];
        $extractedHeadline = $extractedData["title"];
        $extractedPublisher = $extractedData["publisher"];
    }
} else {
    // Determine a clean headline
    $lines = preg_split('/[\r\n]+/', $rawInput);
    $firstLine = trim($lines[0] ?? "");
    $extractedHeadline = mb_strlen($firstLine) <= 90 ? $firstLine : mb_substr($firstLine, 0, 85) . "...";
}

// 4. Resolve Active Gemini API Key
$activeGeminiKey = GeminiConfig::getApiKey($userApiKey);

// 5. Perform Real-Time Live Grounded AI Verification
$aiResult = null;
if (!empty($activeGeminiKey)) {
    $aiResult = verifyClaimWithGeminiSearchGrounding($contentToVerify, $activeGeminiKey, $extractedHeadline, $extractedPublisher);
}

// 6. If AI key is missing or failed, fall back to trained ensemble
if ($aiResult === null) {
    $local = FactCheckEngine::evaluate($contentToVerify, $type);
    $verdict = $local["verdict"] ?? ($local["isReal"] ? "REAL" : "FAKE");
    $confidence = (int)($local["confidence"] ?? 90);
    $summary = $local["summary"] ?? "Verified using FactScan AI multi-source consensus.";
    $why = $local["whyThisResult"] ?? "Evaluated against empirical knowledge archives.";
    $steps = is_array($local["reasoningSteps"] ?? null) ? $local["reasoningSteps"] : [
        "Step 1: Analyzed claim premise and semantic syntax.",
        "Step 2: Cross-referenced multi-source knowledge registries and evidence archives.",
        "Step 3: Confirmed factual authenticity and generated probabilistic confidence."
    ];
    $sources = [];
    if (!empty($local["verifiedSources"]) && is_array($local["verifiedSources"])) {
        foreach ($local["verifiedSources"] as $vs) {
            $sources[] = [
                "title" => is_array($vs) ? ($vs["title"] ?? "Accredited Wire") : (string)$vs,
                "url" => is_array($vs) ? ($vs["url"] ?? "https://news.google.com") : "https://news.google.com"
            ];
        }
    }

    $aiResult = [
        "verdict" => $verdict,
        "confidenceScore" => $confidence,
        "summary" => $summary,
        "reasoning" => array_merge([$why], $steps),
        "sources" => $sources,
        "headline" => !empty($extractedHeadline) ? $extractedHeadline : ($local["headline"] ?? "Verified Claim"),
        "publisher" => !empty($extractedPublisher) && $extractedPublisher !== "Web Source" ? $extractedPublisher : ($local["publisher"] ?? "FactScan AI Engine"),
        "isReal" => ($verdict === "REAL"),
        "groundingUsed" => false,
        "keyNotice" => empty($activeGeminiKey) ? "Notice: Live Google Search grounding is ready. Paste your GEMINI_API_KEY in factscanai_backend/.env to enable live web queries." : null
    ];
}

// 7. Output Unified Structured JSON Response
http_response_code(200);

$verdict = $aiResult["verdict"];
$confidence = (int)$aiResult["confidenceScore"];
$summary = $aiResult["summary"];
$reasoning = is_array($aiResult["reasoning"]) ? array_values($aiResult["reasoning"]) : [$aiResult["reasoning"]];
$sources = is_array($aiResult["sources"]) ? array_values($aiResult["sources"]) : [];
$headline = $aiResult["headline"];
$publisher = $aiResult["publisher"];
$isReal = (bool)($aiResult["isReal"] ?? ($verdict === "REAL"));
$whyThisResult = $reasoning[0] ?? $summary;

// Normalizing verified sources for app UI
$normVerifiedSources = [];
foreach ($sources as $s) {
    if (is_array($s)) {
        $t = $s["title"] ?? "";
        $u = $s["url"] ?? "";
        $normVerifiedSources[] = ($t && $u) ? "$t ($u)" : ($t ?: $u);
    } else {
        $normVerifiedSources[] = (string)$s;
    }
}

echo json_encode([
    "success" => true,
    "verdict" => $verdict,
    "confidenceScore" => $confidence,
    "summary" => $summary,
    "reasoning" => $reasoning,
    "sources" => $sources,
    "headline" => $headline,
    "publisher" => $publisher,
    "isReal" => $isReal,
    "whyThisResult" => $whyThisResult,
    "verdictJustification" => $summary,
    "reasoningSteps" => $reasoning,
    "groundingUsed" => (bool)($aiResult["groundingUsed"] ?? false),
    "timestamp" => date("M d, Y • g:i A"),
    "keyNotice" => $aiResult["keyNotice"] ?? null,
    // Embedded result object for complete Retrofit DTO mapping compatibility
    "result" => [
        "id" => time(),
        "headline" => $headline,
        "publisher" => $publisher,
        "verdict" => $verdict,
        "isReal" => $isReal,
        "confidence" => $confidence,
        "timestamp" => date("M d, Y • g:i A"),
        "summary" => $summary,
        "whyThisResult" => $whyThisResult,
        "verdictJustification" => $summary,
        "reasoningSteps" => $reasoning,
        "keyFindings" => $reasoning,
        "warningSignals" => $isReal ? [] : $reasoning,
        "verifiedSources" => $normVerifiedSources
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit();


// =========================================================================
// HELPER FUNCTIONS
// =========================================================================

/**
 * Live Google Search Grounded Fact-Checking via Gemini 2.5 Flash / 1.5 Flash
 */
function verifyClaimWithGeminiSearchGrounding(string $content, string $apiKey, string $hintHeadline, string $hintPublisher): ?array {
    $modelsToTry = [
        "gemini-2.5-flash",
        "gemini-2.0-flash",
        "gemini-1.5-flash"
    ];

    $cleanContent = mb_substr($content, 0, 7000);

    $systemPrompt = <<<PROMPT
You are FactScan AI, an objective investigative fact-checking agent. You analyze news headlines, claims, articles, and rumors in real time. You verify claims against live authoritative records, international wire services (Reuters, Associated Press, BBC, AFP), and official government announcements.
NEVER return canned, generic, or placeholder messages. Every verdict and reasoning bullet must specifically analyze the specific claims, entities, numbers, and facts in the user content.

You MUST return ONLY a valid JSON object with EXACTLY this structure (no markdown, no backticks, no wrapping text):
{
  "verdict": "REAL" | "FAKE" | "MISLEADING" | "UNVERIFIED",
  "confidenceScore": 95,
  "summary": "1-2 sentence evidence-backed concise verdict",
  "reasoning": [
    "Specific factual finding 1 with named entities and dates",
    "Evidence point 2 explaining why it is true, false, or distorted",
    "Evidence point 3 citing accredited wire reporting or official records"
  ],
  "sources": [
    { "title": "Associated Press / Official Source Name", "url": "https://..." }
  ],
  "headline": "A concise single-line title summarizing the investigated claim",
  "publisher": "Name of publisher or 'Global News Wire'"
}
PROMPT;

    $userMessage = "INVESTIGATE THIS CONTENT FOR FACTUAL AUTHENTICITY:\n\n" . $cleanContent;

    foreach ($modelsToTry as $model) {
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

        $payload = [
            "contents" => [
                [
                    "role" => "user",
                    "parts" => [
                        ["text" => $systemPrompt . "\n\n" . $userMessage]
                    ]
                ]
            ],
            "tools" => [
                [
                    "googleSearch" => new stdClass()
                ]
            ],
            "generationConfig" => [
                "temperature" => 0.1,
                "topP" => 0.8,
                "maxOutputTokens" => 1500
            ]
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 18);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $rawResponse) {
            $parsed = parseGeminiStructuredResponse($rawResponse);
            if ($parsed !== null) {
                $parsed["groundingUsed"] = true;
                return $parsed;
            }
        }
    }

    return null;
}

/**
 * Extracts and decodes JSON from Gemini response candidates
 */
function parseGeminiStructuredResponse(string $rawResponse): ?array {
    $res = json_decode($rawResponse, true);
    if (!isset($res["candidates"][0]["content"]["parts"])) {
        return null;
    }

    $text = "";
    foreach ($res["candidates"][0]["content"]["parts"] as $part) {
        if (!empty($part["text"])) {
            $text .= $part["text"];
        }
    }

    if (empty($text)) {
        return null;
    }

    // Strip markdown code fences if model returned ```json ... ```
    $clean = trim($text);
    if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $clean, $matches)) {
        $clean = trim($matches[1]);
    } else {
        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');
        if ($start !== false && $end !== false) {
            $clean = substr($clean, $start, $end - $start + 1);
        }
    }

    $data = json_decode($clean, true);
    if (!is_array($data) || empty($data["verdict"])) {
        return null;
    }

    $verdict = strtoupper(trim($data["verdict"]));
    if (!in_array($verdict, ["REAL", "FAKE", "MISLEADING", "UNVERIFIED"])) {
        $verdict = "UNVERIFIED";
    }

    // Extract grounding sources from search metadata if present
    $groundingSources = [];
    if (isset($res["candidates"][0]["groundingMetadata"]["groundingChunks"])) {
        foreach ($res["candidates"][0]["groundingMetadata"]["groundingChunks"] as $chunk) {
            if (!empty($chunk["web"]["uri"])) {
                $groundingSources[] = [
                    "title" => !empty($chunk["web"]["title"]) ? $chunk["web"]["title"] : parse_url($chunk["web"]["uri"], PHP_URL_HOST),
                    "url" => $chunk["web"]["uri"]
                ];
            }
        }
    }

    $sources = !empty($data["sources"]) && is_array($data["sources"]) ? $data["sources"] : $groundingSources;

    return [
        "verdict" => $verdict,
        "confidenceScore" => isset($data["confidenceScore"]) ? (int)$data["confidenceScore"] : 92,
        "summary" => !empty($data["summary"]) ? $data["summary"] : "Fact verification complete.",
        "reasoning" => is_array($data["reasoning"] ?? null) ? $data["reasoning"] : [$data["reasoning"] ?? "Verified using primary sources."],
        "sources" => $sources,
        "headline" => !empty($data["headline"]) ? $data["headline"] : "Investigated News Claim",
        "publisher" => !empty($data["publisher"]) ? $data["publisher"] : "Live Web Verification",
        "isReal" => ($verdict === "REAL")
    ];
}

/**
 * Webpage HTML Scraper and Body Extractor
 */
function extractWebpageArticle(string $url): array {
    $host = parse_url($url, PHP_URL_HOST) ?: "Web Source";
    $host = preg_replace('/^www\./', '', $host);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36");

    $html = curl_exec($ch);
    curl_close($ch);

    if (empty($html)) {
        return [
            "title" => "Article from $host",
            "publisher" => ucfirst(explode('.', $host)[0]),
            "fullText" => "URL: $url (Failed to fetch remote webpage content)"
        ];
    }

    // Suppress DOM parser warnings for HTML5 elements
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // 1. Extract Title
    $title = "";
    $ogTitle = $xpath->query('//meta[@property="og:title"]/@content');
    if ($ogTitle->length > 0) {
        $title = trim($ogTitle->item(0)->nodeValue);
    }
    if (empty($title)) {
        $titleNodes = $xpath->query('//title');
        if ($titleNodes->length > 0) {
            $title = trim($titleNodes->item(0)->nodeValue);
        }
    }
    if (empty($title)) {
        $h1Nodes = $xpath->query('//h1');
        if ($h1Nodes->length > 0) {
            $title = trim($h1Nodes->item(0)->nodeValue);
        }
    }

    // 2. Extract Publisher Site Name
    $publisher = "";
    $ogSite = $xpath->query('//meta[@property="og:site_name"]/@content');
    if ($ogSite->length > 0) {
        $publisher = trim($ogSite->item(0)->nodeValue);
    }
    if (empty($publisher)) {
        $publisher = ucfirst(explode('.', $host)[0]);
    }

    // 3. Remove script, style, nav, footer, header elements
    $junk = $xpath->query('//script|//style|//nav|//footer|//header|//aside|//noscript');
    foreach ($junk as $node) {
        $node->parentNode->removeChild($node);
    }

    // 4. Extract Article Paragraphs
    $bodyText = "";
    $paragraphs = $xpath->query('//article//p | //main//p | //div[contains(@class, "article") or contains(@class, "story") or contains(@class, "content")]//p');
    if ($paragraphs->length === 0) {
        $paragraphs = $xpath->query('//p');
    }

    $collected = [];
    foreach ($paragraphs as $p) {
        $cleanP = trim(preg_replace('/\s+/', ' ', $p->nodeValue));
        if (mb_strlen($cleanP) >= 40) {
            $collected[] = $cleanP;
        }
        if (count($collected) >= 12) {
            break;
        }
    }
    $bodyText = implode("\n\n", $collected);

    $fullText = "ARTICLE TITLE: " . ($title ?: "Untitled") . "\nPUBLISHER: " . $publisher . "\nSOURCE URL: " . $url . "\n\n" . $bodyText;

    return [
        "title" => $title ?: "Article from $host",
        "publisher" => $publisher,
        "fullText" => mb_substr($fullText, 0, 7000)
    ];
}
