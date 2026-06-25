<?php
/**
 * Render health check endpoint.
 * Returns 200 if the app is alive and DB is reachable.
 *
 * Render pings this every 5s. If it returns 5xx twice,
 * Render restarts the service.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$status = 'ok';
$httpCode = 200;
$details = [];

// Check PHP version
$details['php_version'] = PHP_VERSION;

// Check database connectivity
try {
    $pdo = getConnection();
    $pdo->query('SELECT 1');
    $details['db'] = 'connected';
} catch (\Throwable $e) {
    $status = 'error';
    $httpCode = 503;
    $details['db'] = 'unreachable';
    $details['db_error'] = $e->getMessage();
}

http_response_code($httpCode);
header('Content-Type: application/json');
echo json_encode([
    'status'  => $status,
    'service' => 'IntelliLearn Quiz Engine',
    'time'    => date('c'),
    'details' => $details,
], JSON_PRETTY_PRINT);
