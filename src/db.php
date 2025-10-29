<?php
// src/db.php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    global $ENV;
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $ENV['DB_HOST'],
        $ENV['DB_PORT'],
        $ENV['DB_DATABASE']
    );
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
        $pdo = new PDO($dsn, $ENV['DB_USERNAME'], $ENV['DB_PASSWORD'], $opts);
        return $pdo;
    } catch (PDOException $e) {
        error_log("DB CONNECTION ERROR: " . $e->getMessage());
        throw new RuntimeException('Database connection failed. Check DB credentials and permissions.');
    }
}
