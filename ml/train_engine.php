<?php
// FactScan AI - High Performance Machine Learning Trainer (PHP Engine)
// Trains a Multinomial Naive Bayes + Bigram N-gram classifier on large_news_dataset.csv

$datasetPath = __DIR__ . "/data/large_news_dataset.csv";
$modelPath = __DIR__ . "/model/trained_classifier.json";

if (!file_exists($datasetPath)) {
    die("Error: Dataset not found at $datasetPath\n");
}

echo "[*] Loading dataset: $datasetPath\n";
$handle = fopen($datasetPath, "r");
$header = fgetcsv($handle);

$textIdx = array_search("text", $header);
$titleIdx = array_search("title", $header);
$labelIdx = array_search("label", $header);

if ($textIdx === false || $labelIdx === false) {
    die("Error: Required columns 'text' and 'label' not found.\n");
}

$stopwords = array_flip([
    "a", "about", "above", "after", "again", "against", "all", "am", "an", "and", "any", "are", "aren't", "as", "at",
    "be", "because", "been", "before", "being", "below", "between", "both", "but", "by", "can't", "cannot", "could",
    "couldn't", "did", "didn't", "do", "does", "doesn't", "doing", "don't", "down", "during", "each", "few", "for",
    "from", "further", "had", "hadn't", "has", "hasn't", "have", "haven't", "having", "he", "he'd", "he'll", "he's",
    "her", "here", "here's", "hers", "herself", "him", "himself", "his", "how", "how's", "i", "i'd", "i'll", "i'm",
    "i've", "if", "in", "into", "is", "isn't", "it", "it's", "its", "itself", "let's", "me", "more", "most", "mustn't",
    "my", "myself", "no", "nor", "not", "of", "off", "on", "once", "only", "or", "other", "ought", "our", "ours",
    "ourselves", "out", "over", "own", "same", "shan't", "she", "she'd", "she'll", "she's", "should", "shouldn't",
    "so", "some", "such", "than", "that", "that's", "the", "their", "theirs", "them", "themselves", "then", "there",
    "there's", "these", "they", "they'd", "they'll", "they're", "they've", "this", "those", "through", "to", "too",
    "under", "until", "up", "very", "was", "wasn't", "we", "we'd", "we'll", "we're", "we've", "were", "weren't",
    "what", "what's", "when", "when's", "where", "where's", "which", "while", "who", "who's", "whom", "why", "why's",
    "with", "won't", "would", "wouldn't", "you", "you'd", "you'll", "you're", "you've", "your", "yours", "yourself",
    "yourselves"
]);

function tokenize($text, $stopwords) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

    $filtered = [];
    foreach ($words as $w) {
        if (strlen($w) >= 2 && !isset($stopwords[$w])) {
            $filtered[] = $w;
        }
    }

    // Add bigrams
    $tokens = $filtered;
    $count = count($filtered);
    for ($i = 0; $i < $count - 1; $i++) {
        $tokens[] = $filtered[$i] . "_" . $filtered[$i + 1];
    }

    return $tokens;
}

$docs = [];
$realCount = 0;
$fakeCount = 0;

$vocab = [];
$realWordCounts = [];
$fakeWordCounts = [];
$docFreq = [];

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) <= max($textIdx, $labelIdx)) continue;

    $title = $titleIdx !== false ? $row[$titleIdx] : "";
    $content = $row[$textIdx];
    $combined = trim($title . " " . $content);
    $label = (int)$row[$labelIdx];

    $tokens = tokenize($combined, $stopwords);
    if (empty($tokens)) continue;

    $docs[] = [
        "tokens" => $tokens,
        "label" => $label,
        "title" => $title
    ];

    if ($label === 1) {
        $realCount++;
    } else {
        $fakeCount++;
    }

    $seenInDoc = [];
    foreach ($tokens as $t) {
        $vocab[$t] = true;
        if ($label === 1) {
            $realWordCounts[$t] = ($realWordCounts[$t] ?? 0) + 1;
        } else {
            $fakeWordCounts[$t] = ($fakeWordCounts[$t] ?? 0) + 1;
        }

        if (!isset($seenInDoc[$t])) {
            $seenInDoc[$t] = true;
            $docFreq[$t] = ($docFreq[$t] ?? 0) + 1;
        }
    }
}
fclose($handle);

$totalDocs = count($docs);
echo "[+] Loaded $totalDocs documents: Real = $realCount, Fake = $fakeCount\n";
echo "[+] Unique vocabulary items (unigrams + bigrams): " . count($vocab) . "\n";

$realPrior = log($realCount / $totalDocs);
$fakePrior = log($fakeCount / $totalDocs);

$totalRealTokens = array_sum($realWordCounts);
$totalFakeTokens = array_sum($fakeWordCounts);
$vocabSize = count($vocab);

// Calculate Laplace smoothed log probabilities and weights
$terms = [];
foreach ($vocab as $term => $_) {
    $cReal = $realWordCounts[$term] ?? 0;
    $cFake = $fakeWordCounts[$term] ?? 0;

    $pReal = log(($cReal + 1) / ($totalRealTokens + $vocabSize));
    $pFake = log(($cFake + 1) / ($totalFakeTokens + $vocabSize));

    // Log-odds weight: positive = real indicator, negative = fake indicator
    $weight = round($pReal - $pFake, 4);

    $terms[$term] = [
        "pReal" => round($pReal, 6),
        "pFake" => round($pFake, 6),
        "weight" => $weight,
        "df" => $docFreq[$term] ?? 1
    ];
}

// Evaluate Model on Training Set
$correct = 0;
$tp = 0; $fp = 0; $tn = 0; $fn = 0;

foreach ($docs as $doc) {
    $scoreReal = $realPrior;
    $scoreFake = $fakePrior;

    foreach ($doc["tokens"] as $t) {
        if (isset($terms[$t])) {
            $scoreReal += $terms[$t]["pReal"];
            $scoreFake += $terms[$t]["pFake"];
        }
    }

    $pred = ($scoreReal >= $scoreFake) ? 1 : 0;
    if ($pred === $doc["label"]) {
        $correct++;
    }

    if ($pred === 1 && $doc["label"] === 1) $tp++;
    if ($pred === 1 && $doc["label"] === 0) $fp++;
    if ($pred === 0 && $doc["label"] === 0) $tn++;
    if ($pred === 0 && $doc["label"] === 1) $fn++;
}

$accuracy = ($correct / $totalDocs) * 100;
$precision = ($tp + $fp) > 0 ? ($tp / ($tp + $fp)) * 100 : 0;
$recall = ($tp + $fn) > 0 ? ($tp / ($tp + $fn)) * 100 : 0;

echo "\n============================================\n";
echo "TRAINING EVALUATION REPORT:\n";
echo "Accuracy:  " . number_format($accuracy, 2) . "%\n";
echo "Precision: " . number_format($precision, 2) . "%\n";
echo "Recall:    " . number_format($recall, 2) . "%\n";
echo "Confusion Matrix: TP=$tp, FP=$fp, TN=$tn, FN=$fn\n";
echo "============================================\n";

// Save model artifact
$modelData = [
    "trainedAt" => date("c"),
    "sampleCount" => $totalDocs,
    "accuracy" => round($accuracy, 2),
    "realPrior" => $realPrior,
    "fakePrior" => $fakePrior,
    "totalRealWords" => $totalRealTokens,
    "totalFakeWords" => $totalFakeTokens,
    "vocabSize" => $vocabSize,
    "terms" => $terms
];

file_put_contents($modelPath, json_encode($modelData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "[+] Successfully saved trained classifier to: $modelPath (" . number_format(filesize($modelPath) / 1024, 1) . " KB)\n";
?>
