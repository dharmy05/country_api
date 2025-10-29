<?php
// public/index.php - web entry point
// Run with: php -S 0.0.0.0:8080 -t public
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

$scriptName = $_SERVER['SCRIPT_NAME'];
if (strpos($path, $scriptName) === 0) {
    $path = substr($path, strlen($scriptName));
}
$path = '/' . trim($path, "/");

error_log("DEBUG >>> inside refresh route");
try {
    if ($method === 'POST' && $path === '/countries/refresh') {
        error_log("DEBUG >>> step 1: starting external fetch");
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
        if ($method === 'GET') {
            $service->get_one($name);
        } elseif ($method === 'DELETE') {
            $service->delete_one($name);
        } else {
            json_response(['error' => 'Method not allowed'], 405);
        }
    }

    if ($method === 'GET' && $path === '/status') {
        $service->status();
    }

    json_response(['error' => 'Not found'], 404);
} catch (Throwable $e) {
    error_log("DEBUG >>> exception: " . $e->getMessage());
    error_log($e->getTraceAsString());
    internal_error('Internal server error');
}
