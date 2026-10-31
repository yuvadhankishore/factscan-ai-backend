<?php
// FactScan AI - Automated Feedback Learning Loop
// Ingests verified user feedback/reports into large_news_dataset.csv and triggers retraining

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/db.php";

$datasetPath = __DIR__ . "/../ml/data/large_news_dataset.csv";
$sampleDatasetPath = __DIR__ . "/../ml/data/sample_dataset.csv";
$trainScript = __DIR__ . "/../ml/train_engine.php";

$ingestedCount = 0;

if ($conn) {
    try {
        // Fetch user feedback reporting inaccurate analysis or submitting new verified claims
        $stmt = $conn->query("
            SELECT id, feedback_type, comment, verification_id, created_at 
            FROM feedback 
            WHERE status = 'APPROVED' OR feedback_type IN ('INACCURATE_ANALYSIS', 'CORRECTION', 'NEW_CLAIM')
            LIMIT 50
        ");
        $feedbackRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($feedbackRows)) {
            $fp = fopen($datasetPath, "a");
            foreach ($feedbackRows as $fb) {
                $comment = trim($fb["comment"]);
                // Check if comment has sufficient context (min 30 characters)
                if (strlen($comment) >= 30) {
                    $isReal = (stripos($comment, "fake") !== false || stripos($comment, "hoax") !== false) ? 0 : 1;
                    $title = substr($comment, 0, 75);
                    fputcsv($fp, [$title, $comment, $isReal]);
                    $ingestedCount++;
                }
            }
            fclose($fp);
            @copy($datasetPath, $sampleDatasetPath);
        }
    } catch (PDOException $e) {
        error_log("Feedback retraining query failed: " . $e->getMessage());
    }
}

// Execute training engine via PHP CLI
$phpBinary = "C:\\xampp\\php\\php.exe";
$output = [];
$returnCode = 0;

if (file_exists($phpBinary) && file_exists($trainScript)) {
    exec("\"$phpBinary\" \"$trainScript\" 2>&1", $output, $returnCode);
}

echo json_encode([
    "success" => ($returnCode === 0),
    "ingestedFeedbackSamples" => $ingestedCount,
    "trainingOutput" => implode("\n", $output),
    "timestamp" => date("c")
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
