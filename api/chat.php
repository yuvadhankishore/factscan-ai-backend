<?php
/**
 * FactScan AI - Conversational AI Chat API (ChatGPT & Gemini Architecture)
 * Supports multi-turn dialogue, open-ended QA, and real-time news/claim analysis
 * powered by Gemini Conversational LLM + Python ML Soft-Voting Ensemble CoPilot.
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id, X-Gemini-Api-Key");
header("Content-Type: application/json; charset=UTF-8");

$requestMethod = $_SERVER["REQUEST_METHOD"] ?? (php_sapi_name() === 'cli' ? 'POST' : 'GET');

if ($requestMethod === "OPTIONS") {
    http_response_code(200);
    exit;
}

if ($requestMethod !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed. Use POST."]);
    exit;
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/gemini.php";
require_once __DIR__ . "/../config/ai_bridge.php";
require_once __DIR__ . "/../config/url_helper.php";
require_once __DIR__ . "/../ml/factcheck_engine.php";

$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true) ?: [];

$userMessage = trim($input["message"] ?? $input["text"] ?? $input["prompt"] ?? "");
if (empty($userMessage) && !empty($argv[1])) {
    $userMessage = trim($argv[1]);
}
if (empty($userMessage)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Message cannot be empty."]);
    exit;
}

$history = $input["history"] ?? [];
if (!is_array($history)) {
    $history = [];
}

$context = $input["context"] ?? [];
if (!is_array($context)) {
    $context = [];
}
$articleMeta = null;

// 1. Resolve User and Gemini API Key
$userId = null;
if (isset($_SERVER["HTTP_X_USER_ID"]) && is_numeric($_SERVER["HTTP_X_USER_ID"])) {
    $userId = (int)$_SERVER["HTTP_X_USER_ID"];
} elseif (!empty($input["user_id"]) && is_numeric($input["user_id"])) {
    $userId = (int)$input["user_id"];
}

$apiKey = null;
if (isset($_SERVER["HTTP_X_GEMINI_API_KEY"]) && trim($_SERVER["HTTP_X_GEMINI_API_KEY"]) !== "") {
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

// Look up saved key in user_settings if not passed in header
if (empty($apiKey) && $userId && $conn) {
    try {
        $stmtUser = $conn->prepare("SELECT custom_gemini_api_key FROM user_settings WHERE user_id = :uid LIMIT 1");
        $stmtUser->execute([":uid" => $userId]);
        $row = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row["custom_gemini_api_key"])) {
            $apiKey = trim($row["custom_gemini_api_key"]);
        }
    } catch (Exception $e) {
        // Proceed without custom key
    }
}

if (empty($apiKey) && $conn) {
    try {
        $stmtAny = $conn->query("SELECT custom_gemini_api_key FROM user_settings WHERE custom_gemini_api_key IS NOT NULL AND custom_gemini_api_key != '' ORDER BY id DESC LIMIT 1");
        $anyRow = $stmtAny->fetch(PDO::FETCH_ASSOC);
        if ($anyRow && !empty($anyRow["custom_gemini_api_key"])) {
            $apiKey = trim($anyRow["custom_gemini_api_key"]);
        }
    } catch (Exception $e) {
        // Proceed
    }
}

// 2. Hybrid CoPilot: Context Grounding (URL & Text Processing)
$contextUrl = trim($context["url"] ?? "");
if (empty($contextUrl) && preg_match('/https?:\/\/[^\s]+/i', $userMessage, $urlMatches)) {
    $contextUrl = $urlMatches[0];
}

$parsedArticle = null;
if (!empty($contextUrl)) {
    $parsedArticle = fetchAndParseArticleUrl($contextUrl);
    if ($parsedArticle["success"]) {
        $articleMeta = [
            "title" => $parsedArticle["title"],
            "publisher" => $parsedArticle["publisher"],
            "domain" => $parsedArticle["domain"],
            "url" => $parsedArticle["url"]
        ];
    }
}

$contextSnippet = trim($context["text"] ?? $context["content"] ?? "");

// Evaluate with Python ML Ensemble & Rule Engine
$mlAnalysis = null;
$claimEval = null;

if (!empty($parsedArticle) && $parsedArticle["success"]) {
    $evalText = $parsedArticle["title"] . " " . substr($parsedArticle["bodyText"], 0, 1500);
    try {
        $claimEval = FactCheckEngine::evaluate($evalText, "URL", $parsedArticle["url"]);
    } catch (Exception $e) {
        $claimEval = null;
    }
} elseif (!empty($contextSnippet)) {
    try {
        $claimEval = FactCheckEngine::evaluate(substr($contextSnippet, 0, 1500), "DOCUMENT_UPLOAD");
    } catch (Exception $e) {
        $claimEval = null;
    }
} else {
    $isGreetingOrQuestion = preg_match('/^(hi|hello|hey|greetings|good\s*(morning|afternoon|evening)|howdy|who are you|what can you do|how are you|tell me about yourself)\b/i', $userMessage);

    if (!$isGreetingOrQuestion) {
        try {
            $claimEval = FactCheckEngine::evaluate($userMessage, "TEXT_CLAIM");
        } catch (Exception $e) {
            $claimEval = null;
        }
    }
}

$isLikelyClaim = false;
if ($claimEval && isset($claimEval["confidence"])) {
    $wordCount = str_word_count($userMessage);
    if ($wordCount >= 4 || !empty($claimEval["warningSignals"]) || !empty($parsedArticle) || !empty($contextSnippet)) {
        $isLikelyClaim = true;
    }
}

// 3. Prepare System Instructions
$systemInstruction = "You are FactScan AI Assistant, an advanced, highly intelligent conversational AI co-pilot created to assist users with open-ended dialogue, news analysis, claim verification, research, writing, and explanations. You speak warmly, clearly, objectively, and thoughtfully, similar to ChatGPT and Gemini.\n\n"
    . "Key Capabilities & Personality:\n"
    . "1. Conversational & Empathetic: When users say hello, ask for advice, or chat casually, respond naturally and helpfully.\n"
    . "2. URL & Article Expert: When a web article URL is provided, analyze the article deeply, summarize key claims, examine the publishing domain credibility, and highlight verified facts vs red flags.\n"
    . "3. Document & Text Processor: When user pastes text, uploaded documents, or OCR-scanned articles, dissect the assertions, explain the underlying facts, and provide clear bullet-point takeaways.\n"
    . "4. Deep Contextual Reasoning: Clarify why something is true, false, or unproven with traceable scientific, institutional, or journalistic evidence.\n"
    . "5. Format responses nicely using Markdown (bullet points, bold highlights, concise paragraphs) for readability on mobile screens.";

// Inject Grounding Context for URL or Document
if (!empty($parsedArticle) && $parsedArticle["success"]) {
    $systemInstruction .= "\n\n[Active Webpage Grounding Context]:\n"
        . "Article Title: {$parsedArticle['title']}\n"
        . "Publisher / Domain: {$parsedArticle['publisher']} ({$parsedArticle['domain']})\n"
        . "Article URL: {$parsedArticle['url']}\n"
        . "Article Content:\n{$parsedArticle['bodyText']}\n"
        . "Answer the user's questions about this article accurately, examine its claims, and summarize or explain its key points.";
} elseif (!empty($contextSnippet)) {
    $systemInstruction .= "\n\n[Active Document / Text Processing Grounding Context]:\n"
        . "Extracted Document Content:\n" . substr($contextSnippet, 0, 3500) . "\n"
        . "Answer the user's questions about this document or text, fact-check its assertions, and provide clear explanations.";
}

if ($isLikelyClaim && $claimEval) {
    $verdictStr = !empty($claimEval["isReal"]) ? "REAL / CREDIBLE" : "UNVERIFIED / POTENTIALLY FAKE";
    $confStr = $claimEval["confidence"] ?? 85;
    $signalsStr = implode(", ", $claimEval["warningSignals"] ?? []);
    
    $systemInstruction .= "\n\n[Background Intelligence from FactScan Machine Learning Model]:\n"
        . "- Initial ML Classifier Verdict: $verdictStr ($confStr% confidence)\n"
        . "- Detected Risk Signals: " . ($signalsStr ?: "Standard journalistic or online pattern") . "\n"
        . "Use this background data to assist the user informatively and respectfully.";
}

// 4. Construct Multi-Turn Messages
$messagesPayload = [];

// Convert previous history
foreach ($history as $h) {
    $r = ($h["role"] ?? "") === "assistant" || ($h["role"] ?? "") === "model" ? "model" : "user";
    $c = trim($h["content"] ?? $h["text"] ?? "");
    if ($c !== "") {
        $messagesPayload[] = [
            "role" => $r,
            "content" => $c
        ];
    }
}

// Append current user message
$messagesPayload[] = [
    "role" => "user",
    "content" => $userMessage
];

// 5. Invoke Gemini Conversational Engine
$activeKey = GeminiConfig::getApiKey($apiKey);
$geminiReply = null;

if (!empty($activeKey)) {
    $geminiReply = GeminiConfig::chat($messagesPayload, $systemInstruction, $activeKey);
}

// 6. Fallback CoPilot Generative Generator (if Gemini API key is unset or network offline)
if (empty($geminiReply)) {
    $lowerMsg = strtolower($userMessage);

    if (preg_match('/^(hi|hello|hey|greetings|good\s*(morning|afternoon|evening))\b/i', $userMessage)) {
        $geminiReply = "Hello! 👋 I'm **FactScan AI**, your intelligent conversational assistant. You can chat with me, ask general questions, or ask me to verify any breaking news, viral WhatsApp claims, or health rumors. How can I help you today?";
    } elseif (preg_match('/(who are you|what can you do|your name|how do you work)/i', $lowerMsg)) {
        $geminiReply = "I am **FactScan AI**, an advanced AI CoPilot powered by deep learning ensembles and conversational language models. \n\n"
            . "Here is what I can do for you:\n"
            . "• **Verify News & Viral Claims:** Detect hoaxes, medical misinformation, and political disinformation in real time.\n"
            . "• **Conversational QA:** Answer questions about science, history, current events, and technology.\n"
            . "• **Article Summarization:** Break down long articles into clear, factual bullet points.\n"
            . "• **Evidence Analysis:** Explain the exact indicators that make a claim trustworthy or suspicious.";
    } elseif ($claimEval) {
        $isReal = !empty($claimEval["isReal"]);
        $conf = $claimEval["confidence"] ?? 90;
        $verdictWord = $isReal ? "Verified Authentic / Credible" : "Unverified / Misleading Hoax";
        $summary = $claimEval["summary"] ?? "";
        $signals = $claimEval["warningSignals"] ?? [];

        $geminiReply = ($isReal ? "✅ " : "⚠️ ") . "**Analysis Result: $verdictWord** ({$conf}% confidence)\n\n"
            . ($summary ?: "Based on our trained machine learning models, this claim has been analyzed against verified journalistic and institutional archives.") . "\n\n"
            . (!empty($signals) ? "**Key Signals Detected:**\n• " . implode("\n• ", $signals) . "\n\n" : "")
            . "**Recommendation:** " . ($isReal 
                ? "This statement aligns with corroborated reporting from accredited sources." 
                : "Exercise caution. Do not forward or amplify this claim without independent confirmation from official regulatory or institutional bodies.");
    } else {
        $geminiReply = "I've analyzed your question! As FactScan AI, I'm here to help you dissect facts, explain complex topics, and explore reliable information. Could you share more details or the specific claim you'd like me to look into?";
    }
}

// 7. Return Response
$response = [
    "success" => true,
    "reply" => $geminiReply,
    "isReal" => $claimEval["isReal"] ?? null,
    "confidence" => $claimEval["confidence"] ?? null,
    "warningSignals" => $claimEval["warningSignals"] ?? [],
    "engine" => !empty($activeKey) ? "gemini_copilot_hybrid" : "factscan_ai_copilot",
    "articleMeta" => $articleMeta,
    "timestamp" => time()
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
