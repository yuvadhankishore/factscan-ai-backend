<?php
// FactScan AI - Python ML Engine Bridge
// Integrates Python ML inference into the PHP Backend

class PythonAiBridge {
    const MICROSERVICE_URL = "http://127.0.0.1:5000/predict";
    const MICROSERVICE_TIMEOUT = 2; // seconds

    /**
     * Predict claim authenticity using Python ML
     * Priority:
     *   1. Local Python HTTP Microservice (http://127.0.0.1:5000/predict)
     *   2. Direct CLI execution (python ml/predict.py)
     *   3. Returns null if Python is unavailable
     */
    public static function predict(string $text): ?array {
        $text = trim($text);
        if ($text === "") {
            return null;
        }

        // Attempt 1: Call Python Microservice via cURL
        $serviceResult = self::callMicroservice($text);
        if ($serviceResult !== null) {
            return $serviceResult;
        }

        // Attempt 2: Direct CLI Execution
        $cliResult = self::callCli($text);
        if ($cliResult !== null) {
            return $cliResult;
        }

        return null;
    }

    private static function callMicroservice(string $text): ?array {
        $ch = curl_init(self::MICROSERVICE_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["text" => $text]));
        curl_setopt($ch, CURLOPT_TIMEOUT, self::MICROSERVICE_TIMEOUT);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (is_array($data) && isset($data["isReal"])) {
                return $data;
            }
        }
        return null;
    }

    private static function callCli(string $text): ?array {
        $scriptPath = realpath(__DIR__ . "/../ml/predict.py");
        if (!$scriptPath || !file_exists($scriptPath)) {
            return null;
        }

        // Windows commands can use python or py
        $pythonCmd = "python";
        
        $descriptorspec = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"]  // stderr
        ];

        $process = @proc_open("$pythonCmd \"$scriptPath\"", $descriptorspec, $pipes);
        if (!is_resource($process)) {
            return null;
        }

        // Send text via stdin to avoid command line length limits and quote escaping bugs
        fwrite($pipes[0], $text);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $returnCode = proc_close($process);
        if ($returnCode === 0 && $stdout) {
            $data = json_decode(trim($stdout), true);
            if (is_array($data) && isset($data["isReal"])) {
                return $data;
            }
        }

        return null;
    }
}
?>