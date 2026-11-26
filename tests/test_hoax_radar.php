<?php
/**
 * FactScan AI - Hoax Radar Feed & Category Classifier Test Suite
 */
require_once __DIR__ . "/../config/hoax_radar_feed.php";

echo "=== HoaxRadarFeed Classification & Aggregation Test ===\n";

assert(HoaxRadarFeed::classifyCategory("New vaccine pill cures cancer") === "Health");
assert(HoaxRadarFeed::classifyCategory("RBI cancels 500 rupee notes and UPI ATM") === "Finance");
assert(HoaxRadarFeed::classifyCategory("Parliament passes emergency election vote") === "Politics");
assert(HoaxRadarFeed::classifyCategory("WhatsApp AI deepfake hack on Android") === "Tech");

$alerts = HoaxRadarFeed::getAlerts(null, "All", 5);
echo "Fetched " . count($alerts) . " radar alerts.\n";
foreach ($alerts as $a) {
    echo " - [" . $a["category"] . " | " . $a["severity"] . "] " . $a["title"] . "\n";
}

echo "PASSED: HoaxRadarFeed verified.\n";

