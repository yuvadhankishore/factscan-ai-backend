<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id");
header("Cache-Control: no-store");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

require_once __DIR__ . "/../config/db.php";

function sendResponse($status, $data) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = $_POST;
}

// User identification
$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId) {
    $userId = filter_var($_GET["user_id"] ?? $_GET["id"] ?? null, FILTER_VALIDATE_INT);
}
if (!$userId && isset($_SERVER["HTTP_X_USER_ID"])) {
    $userId = filter_var($_SERVER["HTTP_X_USER_ID"], FILTER_VALIDATE_INT);
}
if (!$userId && isset($input["user_id"])) {
    $userId = filter_var($input["user_id"], FILTER_VALIDATE_INT);
}

if (!$userId || $userId < 1) {
    sendResponse(401, [
        "success" => false,
        "message" => "Please log in first or specify a valid user_id"
    ]);
}

$method = $_SERVER["REQUEST_METHOD"];

try {
    // 1. GET: List Verification History
    if ($method === "GET") {
        $page = max(1, (int)($_GET["page"] ?? 1));
        $limit = min(50, max(1, (int)($_GET["limit"] ?? 20)));
        $offset = ($page - 1) * $limit;

        $searchQuery = trim($_GET["q"] ?? $_GET["search"] ?? "");
        $verdict = strtolower(trim($_GET["verdict"] ?? "all")); // all, real, fake

        $whereClause = "WHERE v.user_id = :uid";
        $params = [":uid" => $userId];

        if ($searchQuery !== "") {
            $whereClause .= " AND (v.headline LIKE :search OR v.claim_input LIKE :search OR v.publisher LIKE :search)";
            $params[":search"] = "%" . $searchQuery . "%";
        }

        if ($verdict === "real") {
            $whereClause .= " AND v.is_real = 1";
        } elseif ($verdict === "fake") {
            $whereClause .= " AND v.is_real = 0";
        }

        // Count total matching items
        $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM verification_history v $whereClause");
        $stmtCount->execute($params);
        $totalItems = (int)$stmtCount->fetch()["total"];

        // Query items joining saved_verifications to see if currently bookmarked
        $sql = "SELECT 
                    v.id, 
                    v.input_type, 
                    v.claim_input, 
                    v.headline, 
                    v.publisher, 
                    v.is_real, 
                    v.confidence, 
                    v.summary, 
                    v.warning_signals, 
                    v.verified_sources, 
                    v.created_at,
                    IF(s.id IS NOT NULL, 1, 0) AS is_saved
                FROM verification_history v
                LEFT JOIN saved_verifications s ON (v.id = s.verification_id AND s.user_id = :uid_saved)
                $whereClause
                ORDER BY v.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(":uid_saved", $userId, PDO::PARAM_INT);
        $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $historyList = [];
        foreach ($rows as $row) {
            $createdTime = strtotime($row["created_at"]);

            $rawSignals = !empty($row["warning_signals"]) ? json_decode($row["warning_signals"], true) : [];
            if (!is_array($rawSignals)) $rawSignals = [];

            $rawSources = !empty($row["verified_sources"]) ? json_decode($row["verified_sources"], true) : [];
            $normSources = [];
            if (is_array($rawSources)) {
                foreach ($rawSources as $s) {
                    if (is_string($s)) {
                        $normSources[] = $s;
                    } elseif (is_array($s)) {
                        $t = $s["title"] ?? $s["name"] ?? "";
                        $u = $s["url"] ?? "";
                        $d = $s["description"] ?? "";
                        if ($t && $u) {
                            $normSources[] = "$t ($u)";
                        } elseif ($t) {
                            $normSources[] = $t;
                        } elseif ($u) {
                            $normSources[] = $u;
                        } elseif ($d) {
                            $normSources[] = $d;
                        }
                    }
                }
            }

            $historyList[] = [
                "id" => (int)$row["id"],
                "headline" => $row["headline"],
                "publisher" => $row["publisher"],
                "isReal" => (bool)$row["is_real"],
                "confidence" => (int)$row["confidence"],
                "summary" => $row["summary"],
                "timestamp" => date("M d, Y • g:i A", $createdTime),
                "inputType" => $row["input_type"],
                "claimInput" => $row["claim_input"],
                "warningSignals" => $rawSignals,
                "verifiedSources" => $normSources,
                "isSaved" => (bool)$row["is_saved"]
            ];
        }

        sendResponse(200, [
            "success" => true,
            "total" => $totalItems,
            "page" => $page,
            "limit" => $limit,
            "history" => $historyList
        ]);
    }

    // 2. DELETE: Delete single item or clear all
    if ($method === "DELETE") {
        $action = trim($_GET["action"] ?? $input["action"] ?? "");
        $delId = filter_var($_GET["id"] ?? $input["id"] ?? null, FILTER_VALIDATE_INT);

        if ($action === "clear_all") {
            $stmtDel = $conn->prepare("DELETE FROM verification_history WHERE user_id = :uid");
            $stmtDel->execute([":uid" => $userId]);
            sendResponse(200, [
                "success" => true,
                "message" => "All verification history has been cleared successfully"
            ]);
        }

        if ($delId && $delId > 0) {
            $stmtDel = $conn->prepare("DELETE FROM verification_history WHERE id = :id AND user_id = :uid");
            $stmtDel->execute([":id" => $delId, ":uid" => $userId]);

            if ($stmtDel->rowCount() > 0) {
                sendResponse(200, [
                    "success" => true,
                    "message" => "History item removed successfully"
                ]);
            } else {
                sendResponse(404, [
                    "success" => false,
                    "message" => "History item not found or unauthorized"
                ]);
            }
        }

        sendResponse(400, [
            "success" => false,
            "message" => "Specify an 'id' to delete or 'action=clear_all'"
        ]);
    }

    header("Allow: GET, DELETE");
    sendResponse(405, ["success" => false, "message" => "Method not allowed"]);

} catch (PDOException $e) {
    error_log("History operation failed: " . $e->getMessage());
    sendResponse(500, [
        "success" => false,
        "message" => "Database error occurred while accessing verification history"
    ]);
}
?>
