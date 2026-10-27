<?php
/**
 * Application Health Check Endpoint
 * Used by AWS Application Load Balancer (ALB) Target Group to verify container/instance health.
 * Returns HTTP 200 when operational, HTTP 503 when database dependency fails.
 * Strict: Never discloses database credentials, secrets, or internal stack traces.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/config/database.php';

$startTime = microtime(true);
$isHealthy = false;
$dbStatus = 'disconnected';

try {
    if (Database::checkHealth()) {
        $isHealthy = true;
        $dbStatus = 'connected';
    }
} catch (\Throwable $e) {
    $isHealthy = false;
    $dbStatus = 'error';
    error_log("Health check database error: " . $e->getMessage());
}

$responseTimeMs = round((microtime(true) - $startTime) * 1000, 2);

if ($isHealthy) {
    http_response_code(200);
    echo json_encode([
        'status'        => 'healthy',
        'database'      => $dbStatus,
        'app'           => 'Charity Event Calendar',
        'version'       => '1.0.0',
        'timestamp'     => gmdate('c'),
        'latency_ms'    => $responseTimeMs,
        'instance_id'   => gethostname() ?: 'ec2-instance',
    ], JSON_PRETTY_PRINT);
} else {
    http_response_code(503);
    echo json_encode([
        'status'        => 'unhealthy',
        'database'      => $dbStatus,
        'app'           => 'Charity Event Calendar',
        'timestamp'     => gmdate('c'),
        'latency_ms'    => $responseTimeMs,
        'message'       => 'Required service dependency unavailable',
    ], JSON_PRETTY_PRINT);
}
exit;
