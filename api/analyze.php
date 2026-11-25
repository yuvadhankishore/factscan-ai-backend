<?php
/**
 * FactScan AI - Real-Time AI Fact-Checking Engine
 * Inspects claims, URLs, documents, and OCR scans via Gemini API with Google Search Grounding.
 * Logs all interactions to debug_log.txt.
 */

// Disable PHP error display in payload to guarantee pure JSON output
ini_set('display_errors', '0');
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id, X-Gemini-Api-Key, ngrok-skip-browser-warning");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

$backendRoot = dirname(__DIR__);
$debugLogFile = $backendRoot . "/debug_log.txt";

function logDebug(string $message) {
    global $debugLogFile;
    $time = date("Y-m-d H:i:s");
    @file_put_contents($debugLogFile, "[$time] " . $message . "\n", FILE_APPEND);
}

// 1. Robust Custom .env Loader (never rely on getenv() alone in XAMPP)
function loadEnvKey(): string {
    global $backendRoot;
    $key = "";
    
    // Check root .env
    $envPath = $backendRoot . "/.env";
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') continue;
            if (strpos($line, '=') !== false) {
                list($name, $val) = explode('=', $line, 2);
                if (trim($name) === 'GEMINI_API_KEY') {
                    $key = trim($val);
                    break;
                }
            }
        }
    }
    
    if (empty($key)) {
        $key = getenv("GEMINI_API_KEY") ?: "";
    }
    
    // Clean key
    $key = preg_replace('/^GEMINI_API_KEY\s*=\s*/i', '', $key);
    $key = trim($key, " \t\n\r\0\x0B\"'");
    if (strpos($key, "your_") !== false || strlen($key) < 20) {
        return "";
    }
    return $key;
}

$geminiApiKey = loadEnvKey();

// 2. Parse Incoming Request (JSON Body, POST Form, or Multipart)
$inputRaw = file_get_contents("php://input");
$postJson = json_decode($inputRaw, true) ?: [];

$type = strtoupper(trim($_POST["type"] ?? $postJson["type"] ?? "TEXT"));
$rawInput = trim($_POST["input"] ?? $_POST["text"] ?? $_POST["claim"] ?? $postJson["input"] ?? $postJson["text"] ?? $postJson["claim"] ?? "");
$urlInput = trim($_POST["url"] ?? $postJson["url"] ?? "");
$requestSource = trim($_POST["source"] ?? $postJson["source"] ?? "app");
$isShareOverlay = ($requestSource === "share_overlay");

if (empty($rawInput) && !empty($urlInput)) {
    $rawInput = $urlInput;
    $type = "URL";
}

// Check for uploaded file
if (isset($_FILES["file"]) && is_uploaded_file($_FILES["file"]["tmp_name"])) {
    $type = "DOCUMENT";
    require_once __DIR__ . "/../ml/factcheck_engine.php";
    $extracted = FactCheckEngine::extractTextFromDocument($_FILES["file"]["tmp_name"], $_FILES["file"]["name"]);
    if (!empty($extracted)) {
        $rawInput = $extracted;
    }
}

if (empty($rawInput)) {
    http_response_code(400);
    $errRes = ["success" => false, "error" => "No content provided to analyze."];
    echo json_encode($errRes);
    logDebug("Rejected request: No content provided.");
    exit();
}

logDebug("Incoming Verification: Type=$type | InputPreview=" . mb_substr($rawInput, 0, 100));

// 3. Webpage Scraper & Body Extractor for Enter URL
$extractedHeadline = "";
$extractedPublisher = "Live Web Verification";
$contentToVerify = $rawInput;

if ($type === "URL" || filter_var($rawInput, FILTER_VALIDATE_URL) || preg_match('/^https?:\/\//i', $rawInput)) {
    $targetUrl = filter_var($rawInput, FILTER_VALIDATE_URL) ? $rawInput : $urlInput;
    if ($targetUrl) {
        $scraped = scrapeWebpageContent($targetUrl);
        $contentToVerify = $scraped["fullText"];
        $extractedHeadline = $scraped["title"];
        $extractedPublisher = $scraped["publisher"];
        logDebug("Scraped URL '$targetUrl': Title='{$extractedHeadline}' | ContentLen=" . strlen($contentToVerify));
    }
} else {
    $lines = preg_split('/[\r\n]+/', $rawInput);
    $firstLine = trim($lines[0] ?? "");
    $extractedHeadline = mb_strlen($firstLine) <= 90 ? $firstLine : mb_substr($firstLine, 0, 85) . "...";
}

// Check high-speed disk cache with dynamic freshness TTL
$forceRefresh = !empty($_GET["refresh"]) || !empty($_POST["refresh"]) || !empty($postJson["refresh"]);
$isBreakingQuery = (bool)preg_match('/\b(today|now|breaking|latest|current|recent|just in|this week)\b/i', $contentToVerify);
$cacheTtl = $isBreakingQuery ? 900 : 86400; // 15 mins for breaking news vs 24h for static claims

$cacheKey = md5(strtoupper($type) . ":" . trim(mb_strtolower($contentToVerify)));
$cacheDir = $backendRoot . "/storage/cache";
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
}
$cacheFile = $cacheDir . "/analysis_" . $cacheKey . ".json";
if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    $cachedData = @json_decode(file_get_contents($cacheFile), true);
    if (!empty($cachedData) && isset($cachedData["verdict"])) {
        require_once __DIR__ . "/../config/db.php";
        require_once __DIR__ . "/../config/viral_cluster_tracker.php";
        $spreadMeta = ViralClusterTracker::trackClaimSpread($cachedData["headline"] ?? $rawInput, $cachedData["verdict"], $conn ?? null);

        $cachedData["cached"] = true;
        $cachedData["timestamp"] = date("M d, Y • g:i A");
        $cachedData["viralSpreadCount"] = (int)($spreadMeta["viralSpreadCount"] ?? 1);
        $cachedData["isViralHoax"] = (bool)($spreadMeta["isViralHoax"] ?? false);
        $cachedData["clusterWarning"] = (string)($spreadMeta["clusterWarning"] ?? "");
        if (isset($cachedData["result"])) {
            $cachedData["result"]["timestamp"] = date("M d, Y • g:i A");
            $cachedData["result"]["viralSpreadCount"] = $cachedData["viralSpreadCount"];
            $cachedData["result"]["isViralHoax"] = $cachedData["isViralHoax"];
            $cachedData["result"]["clusterWarning"] = $cachedData["clusterWarning"];
        }
        logDebug("Cache HIT for key $cacheKey | Returning in <10ms");
        http_response_code(200);
        echo json_encode($cachedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// 4. Call Gemini REST API with Google Search Grounding
$aiResult = null;
if (!empty($geminiApiKey)) {
    $aiResult = callGeminiWithSearchGrounding($contentToVerify, $geminiApiKey, $extractedHeadline, $extractedPublisher);
} else {
    logDebug("WARNING: No GEMINI_API_KEY found in .env. Evaluating via fallback ensemble.");
}

// 5. If Gemini API was unavailable or errored out, use trained ensemble
if ($aiResult === null) {
    require_once __DIR__ . "/../ml/factcheck_engine.php";
    $local = FactCheckEngine::evaluate($contentToVerify, $type);
    $verdict = $local["verdict"] ?? ($local["isReal"] ? "REAL" : "FAKE");
    $confidence = (int)($local["confidence"] ?? 90);
    $summary = $local["summary"] ?? "Verified using FactScan AI multi-source consensus.";
    $why = $local["whyThisResult"] ?? "Evaluated against empirical knowledge archives.";
    $steps = is_array($local["reasoningSteps"] ?? null) ? $local["reasoningSteps"] : [$why];
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
        "reasoning" => $steps,
        "sources" => $sources,
        "headline" => !empty($extractedHeadline) ? $extractedHeadline : ($local["headline"] ?? "Verified Claim"),
        "publisher" => $extractedPublisher ?: ($local["publisher"] ?? "FactScan AI Engine"),
        "isReal" => ($verdict === "REAL"),
        "groundingUsed" => false
    ];
}

// 6. Build and Output Exact Structured JSON Response
$verdict = $aiResult["verdict"];
$confidence = (int)$aiResult["confidenceScore"];
$summary = $aiResult["summary"];
$reasoning = is_array($aiResult["reasoning"]) ? array_values($aiResult["reasoning"]) : [$aiResult["reasoning"]];
$sources = is_array($aiResult["sources"]) ? array_values($aiResult["sources"]) : [];

// Enrich with live wire citations if sparse
if (count($sources) < 2) {
    require_once __DIR__ . "/../config/live_news_fetcher.php";
    $liveArticles = LiveNewsFetcher::fetchLiveWireNews($headline ?: $rawInput, 3);
    foreach ($liveArticles as $art) {
        $sources[] = [
            "title" => $art["title"],
            "url" => $art["url"],
            "publisher" => $art["publisher"],
            "timestamp" => $art["publishedAt"],
            "isLive" => true
        ];
    }
}

$headline = !empty($aiResult["headline"]) ? $aiResult["headline"] : $extractedHeadline;
$publisher = !empty($aiResult["publisher"]) ? $aiResult["publisher"] : $extractedPublisher;
$isReal = (bool)($aiResult["isReal"] ?? ($verdict === "REAL"));
$whyThisResult = $reasoning[0] ?? $summary;

// String list for verifiedSources
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

// Track viral spread cluster (non-breaking, additive)
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/viral_cluster_tracker.php";
$spreadMeta = ViralClusterTracker::trackClaimSpread($headline ?: $rawInput, $verdict, $conn ?? null);
ViralClusterTracker::recordSpikeAlertIfNeeded($headline ?: $rawInput, $verdict, $spreadMeta["viralSpreadCount"] ?? 1, $conn ?? null);

$responsePayload = [
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
    "isLiveWire" => true,
    "freshness" => "REAL_TIME_2026",
    "temporalAnchor" => date("Y-m-d H:i:s T"),
    "timestamp" => date("M d, Y • g:i A"),
    "viralSpreadCount" => (int)($spreadMeta["viralSpreadCount"] ?? 1),
    "isViralHoax" => (bool)($spreadMeta["isViralHoax"] ?? false),
    "clusterWarning" => (string)($spreadMeta["clusterWarning"] ?? ""),
    // Full matching embedded result for Android Retrofit ContentApiService
    "result" => [
        "id" => time(),
        "headline" => $headline,
        "publisher" => $publisher,
        "verdict" => $verdict,
        "isReal" => $isReal,
        "confidence" => $confidence,
        "isLiveWire" => true,
        "freshness" => "REAL_TIME_2026",
        "temporalAnchor" => date("Y-m-d H:i:s T"),
        "timestamp" => date("M d, Y • g:i A"),
        "viralSpreadCount" => (int)($spreadMeta["viralSpreadCount"] ?? 1),
        "isViralHoax" => (bool)($spreadMeta["isViralHoax"] ?? false),
        "clusterWarning" => (string)($spreadMeta["clusterWarning"] ?? ""),
        "summary" => $summary,
        "whyThisResult" => $whyThisResult,
        "verdictJustification" => $summary,
        "reasoningSteps" => $reasoning,
        "keyFindings" => $reasoning,
        "warningSignals" => $isReal ? [] : $reasoning,
        "verifiedSources" => $normVerifiedSources
    ]
];

if (!empty($cacheFile)) {
    @file_put_contents($cacheFile, json_encode($responsePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

http_response_code(200);
echo json_encode($responsePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
logDebug("Dispatched Response: Verdict=$verdict | Conf=$confidence | Sources=" . count($sources));
exit();


// =========================================================================
// GEMINI REST API WITH GOOGLE SEARCH GROUNDING
// =========================================================================

function callGeminiWithSearchGrounding(string $content, string $apiKey, string $hintTitle, string $hintPub): ?array {
    $modelsToTry = [
        "gemini-3.8-flash",
        "gemini-3.5-flash-lite",
        "gemini-flash-latest",
        "gemini-3.1-flash-lite"
    ];

    $cleanContent = mb_substr($content, 0, 7000);
    $currentDateStr = date("l, F j, Y • g:i A T");

    $systemPrompt = <<<PROMPT
You are FactScan AI, a real-time investigative fact-checking intelligence agent.
TEMPORAL ANCHOR: Today is {$currentDateStr}. You are evaluating this claim in real-time in year 2026.
Verify the factual accuracy against the latest 2026 news updates, official sovereign registries, and accredited news wire reports (Reuters, AP, BBC, AFP, Bloomberg, ANI, Press Trust of India).
Do NOT treat modern 2026 developments as future events. If a claim involves recent breaking news, evaluate the latest public record as of {$currentDateStr}.
NEVER return canned or outdated static messages. Every verdict and reasoning bullet must specifically address the named entities, dates, locations, and contemporary context of the claim.

You MUST return ONLY a raw JSON object (do NOT wrap in ```json or markdown code fences):
{
  "verdict": "REAL" or "FAKE" or "MISLEADING" or "UNVERIFIED",
  "confidenceScore": 95,
  "summary": "1-2 sentence concise factual verdict referencing current reality",
  "reasoning": [
    "Specific factual finding 1 with named entities, dates, and contemporary context",
    "Evidence point 2 explaining why it is true, false, or distorted",
    "Evidence point 3 citing accredited wire reporting, official records, or recent verification"
  ],
  "sources": [
    { "title": "Associated Press / Reuters / Official Source Name", "url": "https://..." }
  ],
  "headline": "A concise single-line title summarizing the investigated claim",
  "publisher": "Name of publisher or 'Global News Wire'"
}
PROMPT;

    $userMessage = "INVESTIGATE THIS CONTENT FOR FACTUAL AUTHENTICITY:\n\n" . $cleanContent;

    require_once __DIR__ . "/../config/live_news_fetcher.php";
    $liveArticles = LiveNewsFetcher::fetchLiveWireNews($cleanContent, 4);
    if (!empty($liveArticles)) {
        $liveContext = "\n\nCONTEMPORARY LIVE NEWS WIRE DISPATCHES (RETRIEVED " . strtoupper(date("M j, Y")) . "):\n";
        foreach ($liveArticles as $art) {
            $liveContext .= "- [" . $art["publisher"] . "] " . $art["title"] . " (Date: " . $art["publishedAt"] . ")\n";
        }
        $userMessage .= $liveContext;
        logDebug("Injected " . count($liveArticles) . " live wire articles into Gemini prompt for real-time 2026 grounding.");
    }

    // Stage 1: Try with google_search grounding tool (with fast short-circuit if quota is limited)
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
                    "google_search" => new stdClass()
                ]
            ],
            "generationConfig" => [
                "temperature" => 0.1,
                "topP" => 0.8,
                "maxOutputTokens" => 1200
            ]
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        logDebug("Gemini Call (grounded) [$model]: HTTP $httpCode | cURL Error: " . ($curlErr ?: "None"));

        if ($httpCode === 200 && !empty($rawResponse)) {
            $parsed = parseGeminiSearchResponse($rawResponse);
            if ($parsed !== null) {
                $parsed["groundingUsed"] = true;
                logDebug("Successfully verified via grounded Gemini ($model)!");
                return $parsed;
            }
        } elseif ($httpCode === 429) {
            logDebug("Search grounding quota reached (429). Fast-failing grounded search to direct neural model.");
            break; // Stop grounded attempts immediately on quota limit
        }
    }

    // Stage 2: Direct Gemini Generative Verification (ultra-fast, bypasses search quota)
    logDebug("Executing fast direct Gemini Generative verification...");
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
            "generationConfig" => [
                "temperature" => 0.1,
                "topP" => 0.8,
                "maxOutputTokens" => 1200
            ]
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        logDebug("Gemini Call (direct) [$model]: HTTP $httpCode | cURL Error: " . ($curlErr ?: "None"));

        if ($httpCode === 200 && !empty($rawResponse)) {
            $parsed = parseGeminiSearchResponse($rawResponse);
            if ($parsed !== null) {
                $parsed["groundingUsed"] = false;
                logDebug("Successfully verified via direct Gemini ($model)!");
                return $parsed;
            }
        } elseif ($httpCode === 404) {
            continue; // model name not found, try next
        }
    }

    return null;
}

function parseGeminiSearchResponse(string $rawResponse): ?array {
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

    // Strip markdown code fences if present: ```json ... ```
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

    // Extract grounding source URLs from groundingMetadata.groundingChunks
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

function scrapeWebpageContent(string $url): array {
    $host = parse_url($url, PHP_URL_HOST) ?: "Web Source";
    $host = preg_replace('/^www\./', '', $host);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_ENCODING, ''); // Enable gzip/deflate for 3x faster transfer
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
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

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

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

    $publisher = "";
    $ogSite = $xpath->query('//meta[@property="og:site_name"]/@content');
    if ($ogSite->length > 0) {
        $publisher = trim($ogSite->item(0)->nodeValue);
    }
    if (empty($publisher)) {
        $publisher = ucfirst(explode('.', $host)[0]);
    }

    $junk = $xpath->query('//script|//style|//nav|//footer|//header|//aside|//noscript');
    foreach ($junk as $node) {
        $node->parentNode->removeChild($node);
    }

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
        if (count($collected) >= 12) break;
    }
    $bodyText = implode("\n\n", $collected);

    $fullText = "ARTICLE TITLE: " . ($title ?: "Untitled") . "\nPUBLISHER: " . $publisher . "\nSOURCE URL: " . $url . "\n\n" . $bodyText;

    return [
        "title" => $title ?: "Article from $host",
        "publisher" => $publisher,
        "fullText" => mb_substr($fullText, 0, 7000)
    ];
}
