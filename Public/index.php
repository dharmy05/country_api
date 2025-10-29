<?php
// public/index.php - front controller (moved from Public/index.php)
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/php_error.log');

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== NULL) {
        error_log("FATAL ERROR: " . json_encode($error));
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error'], JSON_PRETTY_PRINT);
    }
});

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/CountryService.php';

$service = new CountryService();

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
error_log("DEBUG >>> Method: $method | Path: $path");

# If path was internally rewritten as index.php/..., remove the /index.php prefix if present
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
if ($scriptName && strpos($path, $scriptName) === 0) {
    $path = substr($path, strlen($scriptName));
}
$path = '/' . trim($path, "/");

try {
    if ($method === 'POST' && $path === '/countries/refresh') {
        $service->refresh();
    }

    if ($method === 'GET' && $path === '/countries') {
        $service->list($_GET);
    }

    if ($method === 'GET' && preg_match('#^/countries/image$#', $path)) {
        $service->serve_image();
    }

    if (preg_match('#^/countries/([^/]+)$#', $path, $m)) {
        $name = urldecode($m[1]);
     
        // This file was replaced to avoid duplicate front controllers.
        // The canonical front controller is now located at `public/index.php` (lowercase).
        http_response_code(410);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Deprecated',
            'message' => 'This endpoint is deprecated. Use the root URL without "index.php" (e.g. /countries/refresh).'
        ], JSON_PRETTY_PRINT);
//         if ($method === 'GET') {
//             $service->get_one($name);
//         } elseif ($method === 'DELETE') {
//             $service->delete_one($name);
//         } else {
//             json_response(['error' => 'Method not allowed'], 405);
//         }
//     }

//     if ($method === 'GET' && $path === '/status') {
//         $service->status();
//     }

//     json_response(['error' => 'Not found'], 404);
// } catch (Throwable $e) {
//     error_log("DEBUG >>> exception: " . $e->getMessage());
//     error_log($e->getTraceAsString());
//     internal_error('Internal server error');
// }
