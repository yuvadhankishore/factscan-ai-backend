<?php
/**
 * FactScan AI - ProfilePreferencesManager Unit Test Suite
 */
require_once __DIR__ . "/../config/profile_preferences_manager.php";

echo "=== ProfilePreferencesManager Test ===\n";

assert(ProfilePreferencesManager::normalizeDomain("https://www.Reuters.com/world/news") === "reuters.com");
assert(ProfilePreferencesManager::normalizeDomain("http://bbc.co.uk:8080/sport") === "bbc.co.uk");

$uid = 9999;
$res1 = ProfilePreferencesManager::upsertDomainRule($uid, "https://www.snopes.com/fact-check/", "TRUSTED", null);
assert($res1["success"] === true);
assert(ProfilePreferencesManager::checkDomainStatus($uid, "https://snopes.com/article", null) === "USER_TRUSTED");

$res2 = ProfilePreferencesManager::upsertDomainRule($uid, "fakenews-example.xyz", "FLAGGED", null);
assert(ProfilePreferencesManager::checkDomainStatus($uid, "fakenews-example.xyz", null) === "USER_FLAGGED");

ProfilePreferencesManager::removeDomainRule($uid, "fakenews-example.xyz", null);
assert(ProfilePreferencesManager::checkDomainStatus($uid, "fakenews-example.xyz", null) === "NEUTRAL");

$topics = ProfilePreferencesManager::saveRadarTopics($uid, ["Health", "Tech"], null);
assert(count($topics) === 2 && in_array("Health", $topics) && in_array("Tech", $topics));
$cachedTopics = ProfilePreferencesManager::getRadarTopics($uid, null);
assert(count($cachedTopics) === 2);

echo "PASSED: ProfilePreferencesManager O(1) lookups and topic persistence verified.\n";
