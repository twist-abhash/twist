<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Kathmandu');

$host = '127.0.0.1';
$dbName = 'abhash_bids';
$dbUser = 'root';
$dbPass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    $pdo->exec("SET time_zone = '+05:45'");
} catch (Throwable $e) {
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }
    $logFile = $logDir . '/app.log';
    error_log('[' . date('Y-m-d H:i:s') . '] DB CONNECTION ERROR: ' . $e->getMessage() . PHP_EOL, 3, $logFile);
    http_response_code(500);
    exit('Something went wrong. Please try again later.');
}
