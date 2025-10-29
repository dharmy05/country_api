<?php
// src/helpers.php
declare(strict_types=1);

function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    exit;
}

function bad_request(array $details = []): void {
    json_response(['error' => 'Validation failed', 'details' => $details], 400);
}

function not_found(): void {
    json_response(['error' => 'Country not found'], 404);
}

function internal_error(string $message = 'Internal server error'): void {
    json_response(['error' => $message], 500);
}

function service_unavailable(string $which): void {
    json_response([
        'error' => 'External data source unavailable',
        'details' => "Could not fetch data from {$which}"
    ], 503);
}

function http_get_json(string $url, int $timeout = 20): ?array {
    $attempts = 3;
    $connectTimeout = 10;
    $totalTimeout = max($timeout, 30);
    $userAgent = 'CountryCurrencyExchangeAPI/1.0';

    // Optional CA bundle (place certs/cacert.pem in project root)
    $caPath = dirname(__DIR__) . '/certs/cacert.pem';

    for ($i = 1; $i <= $attempts; $i++) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $totalTimeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_FAILONERROR => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => $userAgent,
        ];

        // Enable SSL verification by default; allow CA bundle if present
        $opts[CURLOPT_SSL_VERIFYPEER] = true;
        $opts[CURLOPT_SSL_VERIFYHOST] = 2;
        if (file_exists($caPath)) {
            $opts[CURLOPT_CAINFO] = $caPath;
        }

        curl_setopt_array($ch, $opts);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $connect_time = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
        curl_close($ch);

        if ($errno || $body === false) {
            error_log("HTTP ERROR fetching $url: " . ($error ?: "Unknown cURL error") . " (attempt $i/$attempts) connect_time={$connect_time} total_time={$total_time}");
            if ($i === $attempts) return null;
            sleep(1 * $i);
            continue;
        }

        if ($http_code < 200 || $http_code >= 300) {
            error_log("HTTP FAIL ($http_code) while fetching $url; response snippet: " . substr($body ?? '', 0, 1000) . " (attempt $i/$attempts)");
            if ($http_code >= 500 && $i < $attempts) {
                sleep(1 * $i);
                continue;
            }
            return null;
        }

        $json = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error for $url: " . json_last_error_msg() . "; snippet: " . substr($body, 0, 1000));
            return null;
        }

        return $json;
    }

    return null;
}
