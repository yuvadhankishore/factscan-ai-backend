<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

require_once __DIR__ . "/../config/db.php";

$trendingItems = [];

// 1. Try to fetch recent distinct verified checks from verification_history
if ($conn) {
    try {
        $stmt = $conn->query("
            SELECT id, headline, publisher, is_real, confidence, summary, warning_signals, verified_sources, created_at
            FROM verification_history
            ORDER BY id DESC
            LIMIT 10
        ");
        $dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($dbRows as $row) {
            $signals = json_decode($row["warning_signals"] ?? "[]", true);
            $trendingItems[] = [
                "id" => (int)$row["id"],
                "headline" => $row["headline"],
                "publisher" => $row["publisher"] ?: "FactScan AI Registry",
                "verdict" => (bool)$row["is_real"] ? "REAL" : "FAKE",
                "isReal" => (bool)$row["is_real"],
                "confidence" => (int)$row["confidence"],
                "summary" => $row["summary"],
                "category" => "Recent Check",
                "keyFindings" => is_array($signals) ? array_slice($signals, 0, 3) : [],
                "timestamp" => date("M d, Y", strtotime($row["created_at"]))
            ];
        }
    } catch (PDOException $e) {
        error_log("Failed to load trending from DB: " . $e->getMessage());
    }
}

// 2. If fewer than 6 items, augment with top curated viral hoaxes and real news from large dataset
if (count($trendingItems) < 6) {
    $curated = [
        [
            "id" => 1001,
            "headline" => "Drinking lemon tea completely cures cancer in 48 hours without chemotherapy",
            "publisher" => "Viral WhatsApp Chain Forward",
            "verdict" => "FAKE",
            "isReal" => false,
            "confidence" => 98,
            "summary" => "Contradicted by clinical oncology data. Medical boards confirm citrus peel decoctions cannot destroy systemic cancer cells.",
            "category" => "Health Misinformation",
            "keyFindings" => [
                "No clinical trial or peer-reviewed study exists supporting this claim",
                "Contradicts established oncology consensus and WHO medical registries"
            ],
            "timestamp" => "Trending Today"
        ],
        [
            "id" => 1002,
            "headline" => "NASA confirms 2024 was warmest year on global meteorological record",
            "publisher" => "NASA · science.nasa.gov",
            "verdict" => "REAL",
            "isReal" => true,
            "confidence" => 96,
            "summary" => "Confirmed by global climate tracking stations and satellite instruments across NOAA, NASA, and Copernicus.",
            "category" => "Climate & Space",
            "keyFindings" => [
                "Independently corroborated by satellite temperature tracking",
                "Published across official scientific government registries"
            ],
            "timestamp" => "Verified"
        ],
        [
            "id" => 1003,
            "headline" => "NASA confirms Earth will plunge into total darkness for six days in December",
            "publisher" => "Viral Social Media Meme",
            "verdict" => "FAKE",
            "isReal" => false,
            "confidence" => 98,
            "summary" => "NASA issued no such statement. Solar storm celestial physics cannot extinguish sunlight on Earth.",
            "category" => "Astronomical Hoax",
            "keyFindings" => [
                "Recurring annual internet hoax debunked by NASA Planetary Division",
                "Orbital mechanics confirm continuous solar illumination"
            ],
            "timestamp" => "Trending Hoax"
        ],
        [
            "id" => 1004,
            "headline" => "Reserve Bank of India maintains benchmark repo rate at 6.5 percent",
            "publisher" => "Reserve Bank of India",
            "verdict" => "REAL",
            "isReal" => true,
            "confidence" => 94,
            "summary" => "The Monetary Policy Committee voted unanimously to keep the key policy lending rate unchanged.",
            "category" => "Economy & Finance",
            "keyFindings" => [
                "Official statutory press release issued by the Monetary Policy Committee",
                "Matches global economic news wire reporting"
            ],
            "timestamp" => "Verified"
        ],
        [
            "id" => 1005,
            "headline" => "Government distributing free 50,000 rupees and laptops to all students who click link",
            "publisher" => "Phishing Website Alert",
            "verdict" => "FAKE",
            "isReal" => false,
            "confidence" => 98,
            "summary" => "Fraudulent phishing campaign collecting bank credentials. Ministries confirm no such scheme exists.",
            "category" => "Scam Alert",
            "keyFindings" => [
                "Malicious phishing domain masquerading as government portal",
                "No gazette notification or budget allocation exists"
            ],
            "timestamp" => "High Alert"
        ],
        [
            "id" => 1006,
            "headline" => "James Webb Space Telescope detects atmospheric water vapor on exoplanet",
            "publisher" => "Space Telescope Science Institute",
            "verdict" => "REAL",
            "isReal" => true,
            "confidence" => 95,
            "summary" => "Spectroscopic observations confirmed chemical absorption signatures of water vapor in gas giant exoplanet atmosphere.",
            "category" => "Space Science",
            "keyFindings" => [
                "Peer-reviewed spectroscopic observational dataset published",
                "Primary source data verified by international astrophysicists"
            ],
            "timestamp" => "Verified"
        ]
    ];

    foreach ($curated as $item) {
        if (count($trendingItems) >= 10) break;
        // Avoid duplicate headlines
        $exists = false;
        foreach ($trendingItems as $ti) {
            if (strcasecmp($ti["headline"], $item["headline"]) === 0) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $trendingItems[] = $item;
        }
    }
}

http_response_code(200);
echo json_encode([
    "success" => true,
    "count" => count($trendingItems),
    "trending" => $trendingItems
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
