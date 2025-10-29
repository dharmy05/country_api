<?php
// scripts/test.php - legacy test helper to call refresh (kept in scripts/ for dev)
$url = 'http://127.0.0.1:8080/countries/refresh';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
]);

$resp = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false) {
    echo "Request failed: $err\n";
    exit(1);
}

echo "HTTP $code\n";
echo $resp . PHP_EOL;
