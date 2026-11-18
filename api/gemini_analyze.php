<?php
/**
 * FactScan AI - Dedicated Gemini News Verification API
 * Direct access to Google Gemini 2.0 Flash / Pro multimodal news analysis.
 * Analyzes news in all formats (Text, URL, Document, Image, Audio) and classifies REAL or FAKE.
 */

ini_set('display_errors', '0');
error_reporting(0);
if (ob_get_level() === 0) {
    ob_start();
}

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
    echo json_encode(["success" => false, "message" => "Only POST requests are permitted"]);
    exit;
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/gemini.php";
require_once __DIR__ . "/../config/gemini_reasoning_agent.php";
require_once __DIR__ . "/../config/url_helper.php";

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$input = trim($data["content"] ?? $data["text"] ?? $data["claim"] ?? $data["url"] ?? $data["input"] ?? "");
$format = strtoupper(trim($data["format"] ?? $data["type"] ?? "TEXT_CLAIM"));
$base64Media = $data["media_base64"] ?? $data["image_data"] ?? $data["audio_data"] ?? null;
$mimeType = $data["mime_type"] ?? null;

// Handle file uploads
if (isset($_FILES["media"]) && $_FILES["media"]["error"] === UPLOAD_ERR_OK) {
    $tmp = $_FILES["media"]["tmp_name"];
    $base64Media = base64_encode(file_get_contents($tmp));
    $mimeType = mime_content_type($tmp) ?: "application/octet-stream";
    if (empty($input)) {
        $input = "[Uploaded Media: " . ($_FILES["media"]["name"] ?? "file") . "]";
    }
}

// Extract custom Gemini API Key
$apiKey = $data["apiKey"] ?? $data["api_key"] ?? null;
if (empty($apiKey) && isset($_SERVER["HTTP_X_GEMINI_API_KEY"])) {
    $apiKey = trim($_SERVER["HTTP_X_GEMINI_API_KEY"]);
}

// URL extraction if format is URL
$parsedArticle = null;
if ($format === "URL" || preg_match('/^https?:\/\//i', $input)) {
    $format = "URL";
    if (class_exists("UrlHelper")) {
        $parsedArticle = UrlHelper::fetchAndParseArticleUrl($input);
    }
}

if ($input === "" && empty($base64Media)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "News claim, article URL, document, or media payload is required."
    ]);
    exit;
}

// Execute Gemini Reasoning Analysis across all formats
$result = GeminiReasoningAgent::analyze(
    $input,
    $format,
    $base64Media,
    $mimeType,
    $parsedArticle,
    $apiKey
);

http_response_code(200);
echo json_encode([
    "success" => true,
    "analysisEngine" => $result["geminiReasoningEngine"] ?? "gemini_2_0_flash",
    "format" => $format,
    "result" => $result
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
