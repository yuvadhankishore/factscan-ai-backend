<?php
/**
 * FactScan AI - CSV Export Escaping & Formatting Test
 */
function testFormatCsvRow(array $fields): string {
    $escaped = array_map(function ($val) {
        $s = str_replace('"', '""', (string)$val);
        return '"' . $s . '"';
    }, $fields);
    return implode(',', $escaped) . "\r\n";
}

$row = testFormatCsvRow([1, 'Claim with "quotes", and commas', "FAKE", "2026-11-25"]);
assert(strpos($row, '""quotes""') !== false, "Quotes must be RFC-4180 escaped");
assert(substr($row, -2) === "\r\n", "CSV line endings must be CRLF");
echo "PASSED: CSV formatting and escaping verified.\n";

