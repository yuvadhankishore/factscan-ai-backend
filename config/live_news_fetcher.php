<?php
/**
 * FactScan AI - Live News & Wire Fetcher
 * Fetches real-time breaking news wire reports and articles to ground AI analysis
 * in current 2026 public developments, bypassing LLM static training cutoffs.
 */

class LiveNewsFetcher {

    /**
     * Searches live news wire repositories for a given claim or entity
     */
    public static function fetchLiveWireNews(string $query, int $maxResults = 5): array {
        $cleanQuery = self::extractSearchKeywords($query);
        if (empty($cleanQuery)) return [];

        $rssUrl = "https://news.google.com/rss/search?q=" . urlencode($cleanQuery) . "&hl=en-US&gl=US&ceid=US:en";

        $ch = curl_init($rssUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36");
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $xmlString = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($xmlString)) {
            return [];
        }

        return self::parseRssFeed($xmlString, $maxResults);
    }

    private static function parseRssFeed(string $xmlString, int $maxResults): array {
        $results = [];
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);

        if ($xml === false || !isset($xml->channel->item)) {
            return [];
        }

        foreach ($xml->channel->item as $item) {
            $title = (string)$item->title;
            $link = (string)$item->link;
            $pubDate = (string)$item->pubDate;
            $source = isset($item->source) ? (string)$item->source : "";

            // Extract publisher from title if not separate
            if (empty($source) && strpos($title, " - ") !== false) {
                $parts = explode(" - ", $title);
                $source = array_pop($parts);
                $title = implode(" - ", $parts);
            }

            $results[] = [
                "title" => trim($title),
                "url" => trim($link),
                "publisher" => trim($source) ?: "Accredited Wire",
                "publishedAt" => trim($pubDate),
                "isRecent" => true
            ];

            if (count($results) >= $maxResults) break;
        }

        return $results;
    }

    private static function extractSearchKeywords(string $text): string {
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        // If short, return directly
        $words = explode(' ', $clean);
        if (count($words) <= 7) {
            return implode(' ', $words);
        }

        // Filter out stop words for a concise wire query
        $stopWords = ['the', 'is', 'at', 'which', 'on', 'a', 'an', 'and', 'or', 'in', 'that', 'this', 'to', 'from', 'with', 'by', 'claim', 'fake', 'real', 'news', 'viral'];
        $filtered = array_filter($words, function($w) use ($stopWords) {
            return mb_strlen($w) >= 3 && !in_array(mb_strtolower($w), $stopWords);
        });

        return implode(' ', array_slice($filtered, 0, 6));
    }
}
