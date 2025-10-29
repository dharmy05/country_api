<?php
// src/config.php - configuration loader for project (paths resolved relative to project root)
declare(strict_types=1);

// Project root is parent of src/
$ROOT = dirname(__DIR__);

$ENV['CACHE_DIR'] = $ROOT . '/cache';

function load_env(?string $path = null): array {
    if ($path === null) $path = dirname(__DIR__) . '/.env';
    $env = [];
    if (!file_exists($path)) return $env;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        $v = trim($v, "\"'");
        $env[$k] = $v;
    }
    return $env;
}

$ENV = array_merge([
    'APP_ENV' => 'production',
    'APP_PORT' => '3306',
    'DB_HOST' => 'localhost',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'country_api',
    'DB_USERNAME' => 'oluwadamilola',
    'DB_PASSWORD' => '@olad63',
    'CACHE_DIR' => $ROOT . '/cache'
], load_env($ROOT . '/.env'));

// ensure cache dir exists
if (!is_dir($ENV['CACHE_DIR'])) {
    mkdir($ENV['CACHE_DIR'], 0755, true);
}

return $ENV;
