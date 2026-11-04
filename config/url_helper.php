<?php
/**
 * FactScan AI - URL Article Fetcher & Parser Helper
 * Safely retrieves and extracts title, publisher, body text, and metadata from news URLs.
 */

function fetchAndParseArticleUrl(string $url): array {
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
        return [
            "success" => false,
            "message" => "Please enter a valid HTTP or HTTPS article URL."
        ];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 FactScanAI/2.0',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: no-cache'
        ]
    ]);

    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return [
            "success" => false,
            "message" => "Network error connecting to URL: $curlErr. Please check the link and try again."
        ];
    }

    if ($httpCode === 401 || $httpCode === 403) {
        return [
            "success" => false,
            "message" => "Unable to access the article due to a paywall, subscription requirement, or restricted access (HTTP $httpCode)."
        ];
    }

    if ($httpCode === 404) {
        return [
            "success" => false,
            "message" => "Article not found (HTTP 404). Please verify that the link is correct."
        ];
    }

    if ($httpCode >= 400 || empty($html)) {
        return [
            "success" => false,
            "message" => "Failed to fetch article from this URL (HTTP $httpCode). The server may be blocking automated requests or temporarily down."
        ];
    }

    // Extract Title
    $title = "";
    if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
        $title = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (empty($title) && preg_match('/<meta\s+name=["\']twitter:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
        $title = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (empty($title) && preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $html, $matches)) {
        $title = html_entity_decode(trim(strip_tags($matches[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // Extract Site / Publisher Name
    $publisher = "";
    if (preg_match('/<meta\s+property=["\']og:site_name["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
        $publisher = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    $parsedUrl = parse_url($url);
    $host = $parsedUrl['host'] ?? '';
    $cleanHost = preg_replace('/^www\./i', '', $host);
    if (empty($publisher)) {
        $publisher = $cleanHost ?: "Web Source";
    }

    // Extract Meta Description
    $metaDesc = "";
    if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
        $metaDesc = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // Strip Scripts, Styles, Nav, Footer, and SVG
    $cleanedHtml = preg_replace('/<(script|style|nav|footer|header|aside|svg|iframe)\b[^>]*>.*?<\/\1>/is', '', $html);

    // Extract paragraphs
    preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $cleanedHtml, $pMatches);
    $paragraphs = [];
    foreach ($pMatches[1] as $p) {
        $cleanP = trim(strip_tags(html_entity_decode($p, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if (strlen($cleanP) > 30) {
            $paragraphs[] = $cleanP;
        }
    }

    $bodyText = implode("\n\n", $paragraphs);
    if (empty($bodyText)) {
        $bodyText = trim(strip_tags($cleanedHtml));
        $bodyText = preg_replace('/\s+/', ' ', $bodyText);
    }

    // Truncate cleanly if large
    if (strlen($bodyText) > 4000) {
        $bodyText = substr($bodyText, 0, 4000) . "... [Content truncated for analysis]";
    }

    return [
        "success" => true,
        "title" => $title ?: "Article from $publisher",
        "publisher" => $publisher,
        "domain" => $cleanHost,
        "url" => $url,
        "bodyText" => $bodyText,
        "metaDescription" => $metaDesc
    ];
}

