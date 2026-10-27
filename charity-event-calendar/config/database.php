<?php
/**
 * Database Configuration & Connection Management
 * Handles Amazon RDS MySQL connections, AWS Secrets Manager integration,
 * local .env fallback, and secure PDO connection pooling.
 */

// Load AWS Secrets Manager Helper
require_once __DIR__ . '/aws-secrets.php';

// Simple .env Loader (No third-party dependency needed)
(function() {
    $envPath = __DIR__ . '/../.env';
    if (!file_exists($envPath)) {
        return;
    }
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            // Remove wrapping quotes if present
            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $val;
                putenv("{$key}={$val}");
            }
        }
    }
})();

class Database
{
    private static ?PDO $instance = null;
    private static ?string $lastError = null;

    /**
     * Get or create PDO Database Connection
     */
    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'charity_calendar';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

        // Check if AWS Secrets Manager should be queried
        $useSecrets = filter_var(getenv('USE_AWS_SECRETS') ?: 'false', FILTER_VALIDATE_BOOLEAN);
        $secretName = getenv('AWS_SECRETS_NAME') ?: 'charity-calendar/rds-credentials';
        $awsRegion  = getenv('AWS_REGION') ?: 'us-east-1';

        if ($useSecrets && !empty($secretName)) {
            $secret = AwsSecretsManagerHelper::getSecret($secretName, $awsRegion);
            if ($secret) {
                // AWS RDS Secret standard keys: host, port, dbname/dbClusterIdentifier, username, password
                $host = $secret['host'] ?? $host;
                $port = (string)($secret['port'] ?? $port);
                $name = $secret['dbname'] ?? ($secret['database'] ?? $name);
                $user = $secret['username'] ?? ($secret['user'] ?? $user);
                $pass = $secret['password'] ?? ($secret['pass'] ?? $pass);
            }
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5, // 5s connection timeout
        ];

        try {
            self::$instance = new PDO($dsn, $user, $pass, $options);
            self::$lastError = null;
            return self::$instance;
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            error_log("Database connection error: " . $e->getMessage());
            
            // In production, throw generic message to prevent leaking credentials
            $debug = filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN);
            if ($debug) {
                throw new Exception("Database connection failed: " . $e->getMessage());
            } else {
                throw new Exception("Database service is temporarily unavailable. Please try again later.");
            }
        }
    }

    /**
     * Check if database connection is alive
     */
    public static function checkHealth(): bool
    {
        try {
            $pdo = self::getConnection();
            $stmt = $pdo->query('SELECT 1');
            return $stmt !== false;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Get last connection error (safe for internal logging)
     */
    public static function getLastError(): ?string
    {
        return self::$lastError;
    }
}
