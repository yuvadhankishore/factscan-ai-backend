<?php
/**
 * FactScan AI - Viral Hoax Cluster & Spread Tracker
 * Detects coordinated viral rumors by normalizing claim text, computing
 * keyword fingerprints, and tracking submission frequency across users.
 */

class ViralClusterTracker {

    private static array $stopWords = [
        'the', 'is', 'at', 'which', 'on', 'a', 'an', 'and', 'or', 'in', 'that',
        'this', 'to', 'from', 'with', 'by', 'for', 'of', 'are', 'was', 'were',
        'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did',
        'breaking', 'urgent', 'forward', 'share', 'viral', 'news', 'alert',
        'claims', 'claim', 'says', 'said', 'report', 'reportedly'
    ];

    /**
     * Normalizes raw claim text into sorted, deduplicated significant tokens.
     */
    public static function tokenizeClaim(string $text): array {
        $clean = mb_strtolower($text, 'UTF-8');
        // Strip URLs
        $clean = preg_replace('/https?:\/\/\S+/i', ' ', $clean);
        // Keep only alphanumeric and spaces
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $clean);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        if (empty($clean)) {
            return [];
        }

        $words = explode(' ', $clean);
        $tokens = [];
        foreach ($words as $word) {
            if (mb_strlen($word) >= 3 && !in_array($word, self::$stopWords, true)) {
                $tokens[] = $word;
            }
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);
        return array_slice($tokens, 0, 12);
    }

    /**
     * Computes Jaccard similarity between two token sets (0.0 to 1.0).
     */
    public static function computeSimilarity(array $tokensA, array $tokensB): float {
        if (empty($tokensA) || empty($tokensB)) {
            return 0.0;
        }
        $intersection = count(array_intersect($tokensA, $tokensB));
        $union = count(array_unique(array_merge($tokensA, $tokensB)));
        return $union > 0 ? ($intersection / $union) : 0.0;
    }
}
