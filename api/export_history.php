<?php
/**
 * FactScan AI - Verification History CSV/JSON Export Endpoint
 * GET /api/export_history.php?format=csv|json
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once __DIR__ . "/../config/db.php";

$userId = (int)($_SERVER["HTTP_X_USER_ID"] ?? $_GET["user_id"] ?? 1);
$format = strtolower(trim($_GET["format"] ?? "csv"));

function formatCsvRow(array $fields): string {
    $escaped = array_map(function ($val) {
        $s = str_replace('"', '""', (string)$val);
        return '"' . $s . '"';
    }, $fields);
    return implode(',', $escaped) . "\r\n";
}

$rows = [];
if ($conn instanceof PDO) {
    try {
        $stmt = $conn->prepare("SELECT id, claim_text, verification_result, created_at FROM verification_history WHERE user_id = :uid ORDER BY id DESC LIMIT 200");
        $stmt->execute([":uid" => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $rows = [];
    }
}

if ($format === "json") {
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["success" => true, "count" => count($rows), "records" => $rows]);
    exit();
}

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"factscan_history_" . date("Ymd") . ".csv\"");

echo formatCsvRow(["ID", "Claim / Headline", "Verdict", "Checked At"]);
foreach ($rows as $r) {
    echo formatCsvRow([
        $r["id"] ?? "",
        $r["claim_text"] ?? "",
        $r["verification_result"] ?? "",
        $r["created_at"] ?? ""
    ]);
}
