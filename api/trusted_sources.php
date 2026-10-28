<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id");
header("Cache-Control: public, max-age=3600");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Only GET requests are allowed"]);
    exit;
}

require_once __DIR__ . "/../config/db.php";

try {
    $category = trim($_GET["category"] ?? "");
    $search = trim($_GET["q"] ?? $_GET["search"] ?? "");

    $sql = "SELECT id, name, category, url, icon_url, description, trust_rating 
            FROM trusted_apps 
            WHERE is_active = 1";
    $params = [];

    if ($category !== "") {
        $sql .= " AND category = :cat";
        $params[":cat"] = $category;
    }

    if ($search !== "") {
        $sql .= " AND (name LIKE :search OR description LIKE :search)";
        $params[":search"] = "%" . $search . "%";
    }

    $sql .= " ORDER BY trust_rating DESC, name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $sources = $stmt->fetchAll();

    $list = [];
    foreach ($sources as $s) {
        $list[] = [
            "id" => (int)$s["id"],
            "name" => $s["name"],
            "category" => $s["category"],
            "url" => $s["url"],
            "iconUrl" => $s["icon_url"],
            "description" => $s["description"],
            "trustRating" => (float)$s["trust_rating"]
        ];
    }

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "total" => count($list),
        "sources" => $list
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Trusted sources fetch error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to load trusted fact-checking sources"
    ]);
}
?>