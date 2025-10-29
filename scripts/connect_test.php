<?php
// scripts/connect_test.php - legacy debug helper (kept in scripts/ for dev)
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

try {
    $pdo = get_pdo();
    echo "OK: Connected to database.\n";
    $version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    echo "Server version: " . $version . "\n";
    exit(0);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
