<?php
/**
 * FactScan AI - Knowledge Database Provider
 * Queries indexed factual repositories, sovereign entities, and known hoax signatures.
 */

require_once __DIR__ . "/db.php";

class KnowledgeDatabase {
    private static ?array $cachedFacts = null;

    /**
     * Look up entity facts by name or claim substring
     */
    public static function lookupEntity(string $query): ?array {
        $query = trim(strtolower($query));
        if (empty($query)) {
            return null;
        }

        // 1. In-memory fast cache for core entities
        $known = [
            "india" => [
                "entity" => "Republic of India",
                "domain" => "Geopolitics",
                "status" => "Sovereign Country",
                "capital" => "New Delhi",
                "subdivisions" => "28 states and 8 union territories",
                "is_country" => true,
                "debunk" => "India is an independent sovereign federal republic with 28 states and 8 union territories."
            ],
            "united states" => [
                "entity" => "United States of America",
                "domain" => "Geopolitics",
                "status" => "Sovereign Federal Republic",
                "capital" => "Washington, D.C.",
                "subdivisions" => "50 states and federal district",
                "is_country" => true,
                "debunk" => "The United States is an independent sovereign federal republic."
            ],
            "california" => [
                "entity" => "State of California",
                "domain" => "Geopolitics",
                "status" => "Constituent State of USA",
                "capital" => "Sacramento",
                "subdivisions" => "58 counties",
                "is_country" => false,
                "parent_country" => "United States",
                "debunk" => "California is a constituent state of the United States without independent sovereign UN status."
            ]
        ];

        foreach ($known as $k => $v) {
            if (strpos($query, $k) !== false) {
                return $v;
            }
        }

        // 2. Query database if available
        try {
            $pdo = Database::getConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM factscan_knowledge_facts WHERE LOWER(entity_name) LIKE :q OR LOWER(claim_pattern) LIKE :q LIMIT 1");
                $stmt->execute([":q" => "%" . $query . "%"]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return [
                        "entity" => $row["official_title"],
                        "domain" => $row["domain"],
                        "status" => $row["is_factual"] ? "Verified Fact" : "Debunked Assertion",
                        "capital" => $row["capital_or_context"],
                        "subdivisions" => $row["subdivisions"],
                        "is_country" => (bool)$row["is_factual"],
                        "debunk" => $row["debunk_rationale"]
                    ];
                }
            }
        } catch (Exception $e) {
            // Graceful fallback to static cache
        }

        return null;
    }
}
?>

