<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id, X-Gemini-Api-Key");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Only POST requests are allowed"]);
    exit;
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/gemini.php";
require_once __DIR__ . "/../config/ai_bridge.php";
require_once __DIR__ . "/../ml/factcheck_engine.php";

/**
 * Fetch webpage content and extract core article metadata and body text,
 * stripping boilerplate HTML, scripts, ads, and navigation menus.
 */
function fetchAndParseArticleUrl(string $url): array {
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
        return [
            "success" => false,
            "message" => "Please enter a valid HTTP or HTTPS article URL."
        ];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 FactScanAI/2.0',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: no-cache'
        ]
    ]);

    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return [
            "success" => false,
            "message" => "Network error connecting to URL: $curlErr. Please check the link and try again."
        ];
    }

    if ($httpCode === 401 || $httpCode === 403) {
        return [
            "success" => false,
            "message" => "Unable to access the article due to a paywall, subscription requirement, or restricted access (HTTP $httpCode)."
        ];
    }

    if ($httpCode === 404) {
        return [
            "success" => false,
            "message" => "Article not found (HTTP 404). Please verify that the link is correct."
        ];
    }

    if ($httpCode >= 400 || empty($html)) {
        return [
            "success" => false,
            "message" => "Failed to fetch article from this URL (HTTP $httpCode). The server may be blocking automated requests or temporarily down."
        ];
    }

    // Extract Title
    $title = "";
    if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
        $title = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (empty($title) && preg_match('/<meta\s+name=["\']twitter:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
        $title = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (empty($title) && preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $html, $matches)) {
        $title = html_entity_decode(trim(strip_tags($matches[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // Extract Publisher / Site Name
    $publisher = "";
    if (preg_match('/<meta\s+property=["\']og:site_name["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
        $publisher = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (empty($publisher) && preg_match('/<meta\s+name=["\']author["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
        $publisher = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (empty($publisher)) {
        $host = parse_url($url, PHP_URL_HOST);
        $publisher = preg_replace('/^www\./i', '', $host ?: 'Web Source');
    }

    // Strip boilerplate HTML
    $cleanHtml = preg_replace('/<(script|style|nav|footer|header|aside|iframe|noscript|form|svg)\b[^>]*>.*?<\/\1>/is', ' ', $html);

    $articleHtml = "";
    if (preg_match('/<article\b[^>]*>(.*?)<\/article>/is', $cleanHtml, $matches)) {
        $articleHtml = $matches[1];
    } elseif (preg_match('/<main\b[^>]*>(.*?)<\/main>/is', $cleanHtml, $matches)) {
        $articleHtml = $matches[1];
    } else {
        $articleHtml = $cleanHtml;
    }

    $paragraphs = [];
    if (preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $articleHtml, $matches)) {
        foreach ($matches[1] as $p) {
            $pText = trim(strip_tags($p));
            $pText = html_entity_decode($pText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (strlen($pText) > 25 && !preg_match('/(cookie|privacy policy|terms of service|all rights reserved|subscribe now|sign up)/i', $pText)) {
                $paragraphs[] = $pText;
            }
        }
    }

    $bodyText = implode("\n\n", $paragraphs);
    if (strlen(trim($bodyText)) < 80) {
        $fallbackText = strip_tags($cleanHtml);
        $fallbackText = html_entity_decode($fallbackText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $fallbackText = preg_replace('/\s+/', ' ', $fallbackText);
        $bodyText = trim($fallbackText);
    }

    if (strlen(trim($bodyText)) < 40) {
        return [
            "success" => false,
            "message" => "Unable to extract readable article text from this page. It may be protected behind a paywall, dynamic JavaScript rendering, or login gate."
        ];
    }

    if (empty($title)) {
        $title = substr($bodyText, 0, 80) . "...";
    }

    return [
        "success" => true,
        "title" => $title,
        "publisher" => $publisher,
        "bodyText" => substr($bodyText, 0, 4500),
        "url" => $url
    ];
}

// 1. Parse Request Payload
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$input = trim($data["input"] ?? $data["claim"] ?? $data["text"] ?? $data["claim_input"] ?? $data["content"] ?? $data["url"] ?? "");
$type = strtoupper(trim($data["type"] ?? ""));

if (empty($type)) {
    if (isset($data["url"]) || preg_match('/^https?:\/\//i', $input)) {
        $type = "URL";
    } else {
        $type = "TEXT_CLAIM";
    }
}
if (!in_array($type, ["URL", "TEXT_CLAIM", "DOCUMENT_UPLOAD"])) {
    $type = "TEXT_CLAIM";
}

// Support multipart file uploads for DOCUMENT_UPLOAD (PDF, DOCX, TXT, etc.)
if (empty($input) && isset($_FILES["file"]) && $_FILES["file"]["error"] === UPLOAD_ERR_OK) {
    $input = FactCheckEngine::extractTextFromDocument($_FILES["file"]["tmp_name"], $_FILES["file"]["name"] ?? "");
} elseif (empty($input) && isset($_FILES["document"]) && $_FILES["document"]["error"] === UPLOAD_ERR_OK) {
    $input = FactCheckEngine::extractTextFromDocument($_FILES["document"]["tmp_name"], $_FILES["document"]["name"] ?? "");
}

if ($input === "") {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Input claim, URL, or document content is required for analysis"
    ]);
    exit;
}

// Resolve user identity if authenticated
$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId) {
    $userId = filter_var($_GET["user_id"] ?? $_GET["id"] ?? null, FILTER_VALIDATE_INT);
}
if (!$userId && isset($_SERVER["HTTP_X_USER_ID"])) {
    $userId = filter_var($_SERVER["HTTP_X_USER_ID"], FILTER_VALIDATE_INT);
}
if (!$userId && isset($data["user_id"])) {
    $userId = filter_var($data["user_id"], FILTER_VALIDATE_INT);
}

// Extract custom Gemini API Key from multiple sources
$apiKey = $data["apiKey"] ?? $data["api_key"] ?? null;
if (empty($apiKey) && isset($_SERVER["HTTP_X_GEMINI_API_KEY"])) {
    $apiKey = trim($_SERVER["HTTP_X_GEMINI_API_KEY"]);
}
if (empty($apiKey) && function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $k => $v) {
        if (strcasecmp($k, 'X-Gemini-Api-Key') === 0 && !empty(trim($v))) {
            $apiKey = trim($v);
            break;
        }
    }
}

$autoSave = true;

if ($userId && $userId > 0 && $conn) {
    try {
        $stmtUser = $conn->prepare("SELECT auto_save_history, custom_gemini_api_key FROM user_settings WHERE user_id = :uid LIMIT 1");
        $stmtUser->execute([":uid" => $userId]);
        $uSettings = $stmtUser->fetch();
        if ($uSettings) {
            $autoSave = (bool)$uSettings["auto_save_history"];
            if (empty($apiKey) && !empty($uSettings["custom_gemini_api_key"])) {
                $apiKey = $uSettings["custom_gemini_api_key"];
            }
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch user settings for factcheck: " . $e->getMessage());
    }
}

// If still empty, check if any user configured a key in the database
if (empty($apiKey) && $conn) {
    try {
        $stmtAnyKey = $conn->query("SELECT custom_gemini_api_key FROM user_settings WHERE custom_gemini_api_key IS NOT NULL AND custom_gemini_api_key != '' ORDER BY id DESC LIMIT 1");
        $anyRow = $stmtAnyKey->fetch();
        if ($anyRow && !empty($anyRow["custom_gemini_api_key"])) {
            $apiKey = $anyRow["custom_gemini_api_key"];
        }
    } catch (PDOException $e) {}
}

// When type is URL: Fetch webpage, extract core content & strip boilerplate
$parsedArticle = null;
if ($type === "URL") {
    $parsedArticle = fetchAndParseArticleUrl($input);
    if (!$parsedArticle["success"]) {
        http_response_code(422);
        echo json_encode([
            "success" => false,
            "message" => $parsedArticle["message"]
        ]);
        exit;
    }
}

$result = null;
$rawGemini = null;
$analysisEngine = "intelligent_fact_engine";

// 1. Live Gemini AI Verification
$activeKey = GeminiConfig::getApiKey($apiKey);
if (!empty($activeKey)) {
    if ($type === "URL" && $parsedArticle) {
        $prompt = <<<EOT
You are an expert fact-checking and news verification system. Analyze the provided article text and URL.
Determine whether the content is 'REAL', 'FAKE', or 'MISLEADING'.
Rules:
1. If the article contains unverified claims, fabricated scientific myths, conspiracy theories, or comes from known satire/hoax domains, mark as 'FAKE'.
2. If the headline is clickbait, sensationalized, distorts context, or makes unbacked claims while the text is inconclusive, mark as 'MISLEADING'.
3. If the article reports corroborated facts, accredited statements, peer-reviewed research, or comes from reputable institutions, mark as 'REAL'.

Article URL: {$parsedArticle['url']}
Article Title: {$parsedArticle['title']}
Publisher / Source: {$parsedArticle['publisher']}
Article Content:
{$parsedArticle['bodyText']}

Return ONLY a clean JSON object (no markdown formatting, no code block fences, no extra text) with this exact schema:
{
  "verdict": "REAL",
  "confidence": 92,
  "headline": "A concise headline for this article",
  "publisher": "{$parsedArticle['publisher']}",
  "summary": "Two sentences summarizing the article and its factual basis.",
  "keyFindings": [
    "First finding explaining why verified, fabricated, or biased",
    "Second finding explaining corroborating evidence or lack thereof",
    "Third finding detailing source credibility"
  ],
  "warningSignals": [
    "Evidence or credibility note 1",
    "Evidence or credibility note 2"
  ],
  "verifiedSources": [
    {
      "title": "Reuters Fact Check",
      "url": "https://www.reuters.com/fact-check",
      "description": "Corroborated by independent news records"
    }
  ]
}
EOT;
    } else {
        $prompt = <<<EOT
You are FactScan AI, an expert international news verification and fake news detection engine.
Analyze the following news claim or submitted content:
"$input"

Determine whether this claim is 'REAL', 'FAKE', or 'MISLEADING':
- FAKE: Fabricated hoaxes (e.g. Earth going dark for days, lemon water cures cancer, garlic cures heart attack, free 50k money distribution, 2000 rupee note GPS chip, microchips in vaccines, death hoaxes).
- MISLEADING: Clickbait, exaggerated statistics, partial truths taken out of context, sensationalized claims.
- REAL: Verified news, accredited announcements (NASA, WHO, RBI, government gazettes, peer-reviewed studies).

Return ONLY a clean JSON object (no markdown formatting, no code block backticks, no extra text) with this exact schema:
{
  "verdict": "REAL",
  "headline": "A clean, concise 1-line headline describing the claim",
  "publisher": "Identified source or 'Unverified Viral Claim'",
  "isReal": true,
  "confidence": 92,
  "summary": "2-line evidence-backed factual explanation of why this is true, false, or misleading.",
  "keyFindings": [
    "Specific finding explaining factual verification or debunking",
    "Evidence status across international news and scientific registries"
  ],
  "warningSignals": [
    "Warning or credibility note 1",
    "Warning or credibility note 2"
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

    $geminiResponse = GeminiConfig::generateContent($prompt, $activeKey);
    if ($geminiResponse) {
        $rawGemini = $geminiResponse;
        $jsonStr = "";
        if (preg_match('/\{[\s\S]*\}/', $geminiResponse, $matches)) {
            $jsonStr = $matches[0];
        } else {
            $jsonStr = trim($geminiResponse);
        }

        $parsed = json_decode($jsonStr, true);
        if (is_array($parsed) && isset($parsed["verdict"])) {
            $verdict = strtoupper(trim($parsed["verdict"]));
            if (!in_array($verdict, ["REAL", "FAKE", "MISLEADING"])) {
                $verdict = !empty($parsed["isReal"]) ? "REAL" : "FAKE";
            }
            $isReal = ($verdict === "REAL");

            $keyFindings = is_array($parsed["keyFindings"] ?? null) ? $parsed["keyFindings"] : [];
            $warningSignals = is_array($parsed["warningSignals"] ?? null) ? $parsed["warningSignals"] : [];
            if (empty($keyFindings) && !empty($warningSignals)) {
                $keyFindings = $warningSignals;
            }

            $confidence = (int)($parsed["confidence"] ?? 90);
            if ($confidence < 50 || $confidence > 99) {
                $confidence = 91;
            }

            $result = [
                "headline" => (string)($parsed["headline"] ?? ($parsedArticle ? $parsedArticle["title"] : substr($input, 0, 70))),
                "publisher" => (string)($parsed["publisher"] ?? ($parsedArticle ? $parsedArticle["publisher"] : "AI Verified Source")),
                "verdict" => $verdict,
                "isReal" => $isReal,
                "confidence" => $confidence,
                "summary" => (string)($parsed["summary"] ?? "Analysis corroborated by independent verification records."),
                "keyFindings" => $keyFindings,
                "warningSignals" => $warningSignals,
                "verifiedSources" => is_array($parsed["verifiedSources"] ?? null) ? $parsed["verifiedSources"] : []
            ];
            $analysisEngine = "gemini_live_ai";
        }
    }
}

// 2. Trained Machine Learning & Semantic Knowledge Verification Engine
if ($result === null) {
    $textToAnalyze = ($type === "URL" && $parsedArticle) 
        ? ($parsedArticle["title"] . " " . $parsedArticle["bodyText"]) 
        : $input;
    $sourceUrl = ($type === "URL") ? $input : null;

    $eval = FactCheckEngine::evaluate($textToAnalyze, $type, $sourceUrl);

    if ($type === "URL" && $parsedArticle) {
        $eval["headline"] = !empty($parsedArticle["title"]) ? $parsedArticle["title"] : $eval["headline"];
        $eval["publisher"] = !empty($parsedArticle["publisher"]) ? $parsedArticle["publisher"] : $eval["publisher"];
    }

    $result = $eval;
    $analysisEngine = $eval["engine"] ?? "factscan_trained_ml_nb";
}

// 3. Database Persistence (History & Analytics)
$newVerificationId = null;
if ($autoSave && $conn) {
    try {
        // Persist to verification_history (compatible with history, saved, and personal analysis APIs)
        $inputText = ($type === "URL" && $parsedArticle) ? ($parsedArticle["title"] . "\n\n" . $parsedArticle["bodyText"]) : $input;
        $signalsJson = json_encode($result["keyFindings"] ?: $result["warningSignals"], JSON_UNESCAPED_UNICODE);
        $sourcesJson = json_encode($result["verifiedSources"] ?? [], JSON_UNESCAPED_UNICODE);

        if ($userId && $userId > 0) {
            $stmtInsert = $conn->prepare("
                INSERT INTO verification_history (
                    user_id, input_type, claim_input, headline, publisher,
                    is_real, confidence, summary, warning_signals, verified_sources,
                    raw_gemini_response
                ) VALUES (
                    :user_id, :input_type, :claim_input, :headline, :publisher,
                    :is_real, :confidence, :summary, :warning_signals, :verified_sources,
                    :raw_gemini_response
                )
            ");

            $stmtInsert->execute([
                ":user_id" => $userId,
                ":input_type" => $type,
                ":claim_input" => substr($inputText, 0, 5000),
                ":headline" => substr($result["headline"], 0, 250),
                ":publisher" => substr($result["publisher"], 0, 95),
                ":is_real" => $result["isReal"] ? 1 : 0,
                ":confidence" => $result["confidence"],
                ":summary" => $result["summary"],
                ":warning_signals" => $signalsJson,
                ":verified_sources" => $sourcesJson,
                ":raw_gemini_response" => $rawGemini ?: ($analysisEngine ?? "trained_ml")
            ]);

            $newVerificationId = (int)$conn->lastInsertId();
        }
    } catch (PDOException $e) {
        error_log("Failed to persist verification result: " . $e->getMessage());
    }
}

// 4. Return Output Matching Expected Client Schema
$normVerifiedSources = [];
if (!empty($result["verifiedSources"]) && is_array($result["verifiedSources"])) {
    foreach ($result["verifiedSources"] as $vs) {
        if (is_string($vs)) {
            $normVerifiedSources[] = $vs;
        } elseif (is_array($vs)) {
            $t = $vs["title"] ?? $vs["name"] ?? "";
            $u = $vs["url"] ?? "";
            $d = $vs["description"] ?? "";
            if ($t && $u) {
                $normVerifiedSources[] = "$t ($u)";
            } elseif ($t) {
                $normVerifiedSources[] = $t;
            } elseif ($u) {
                $normVerifiedSources[] = $u;
            } elseif ($d) {
                $normVerifiedSources[] = $d;
            }
        }
    }
}

http_response_code(200);
echo json_encode([
    "success" => true,
    "analysisEngine" => $analysisEngine,
    "result" => [
        "id" => $newVerificationId ?: time(),
        "headline" => $result["headline"],
        "publisher" => $result["publisher"],
        "verdict" => $result["verdict"],
        "isReal" => $result["isReal"],
        "confidence" => $result["confidence"],
        "timestamp" => date("M d, Y • g:i A"),
        "isSaved" => false,
        "summary" => $result["summary"],
        "keyFindings" => is_array($result["keyFindings"]) ? array_values($result["keyFindings"]) : [],
        "warningSignals" => is_array($result["warningSignals"]) ? array_values($result["warningSignals"]) : [],
        "verifiedSources" => $normVerifiedSources
    ]
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>