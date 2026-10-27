<?php
/**
 * AWS Secrets Manager Helper
 * Retrieves database credentials securely using EC2 IAM Role credentials
 * Supports AWS SDK if available, or direct SigV4 REST API via EC2 IMDSv2.
 */

class AwsSecretsManagerHelper
{
    private static ?array $cachedSecret = null;
    private static int $cacheExpiry = 0;
    private const CACHE_TTL_SECONDS = 900; // 15 minutes cache to reduce API calls & cost

    /**
     * Retrieve secret value by secret name or ARN
     */
    public static function getSecret(string $secretName, string $region = 'us-east-1'): ?array
    {
        // 1. Check in-memory cache
        if (self::$cachedSecret !== null && time() < self::$cacheExpiry) {
            return self::$cachedSecret;
        }

        // 2. Check disk-based temporary cache in system temp (with strict permissions)
        $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'charity_db_secret_' . md5($secretName) . '.json';
        if (file_exists($cacheFile)) {
            $cacheContent = @file_get_contents($cacheFile);
            if ($cacheContent) {
                $cached = json_decode($cacheContent, true);
                if (is_array($cached) && isset($cached['_expires']) && $cached['_expires'] > time()) {
                    unset($cached['_expires']);
                    self::$cachedSecret = $cached;
                    self::$cacheExpiry = time() + self::CACHE_TTL_SECONDS;
                    return $cached;
                }
            }
        }

        // 3. Try official AWS SDK if installed via Composer
        $sdkAutoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($sdkAutoload)) {
            require_once $sdkAutoload;
            if (class_exists('Aws\SecretsManager\SecretsManagerClient')) {
                try {
                    $client = new \Aws\SecretsManager\SecretsManagerClient([
                        'version' => 'latest',
                        'region'  => $region,
                    ]);
                    $result = $client->getSecretValue([
                        'SecretId' => $secretName,
                    ]);
                    if (isset($result['SecretString'])) {
                        $secret = json_decode($result['SecretString'], true);
                        if (is_array($secret)) {
                            self::cacheSecret($cacheFile, $secret);
                            return $secret;
                        }
                    }
                } catch (\Exception $e) {
                    error_log('AWS SDK Secrets Manager error: ' . $e->getMessage());
                }
            }
        }

        // 4. Fallback: EC2 IMDSv2 + AWS SigV4 REST API (Pure PHP, zero external dependencies)
        $secret = self::fetchViaEc2IamRole($secretName, $region);
        if ($secret !== null) {
            self::cacheSecret($cacheFile, $secret);
            return $secret;
        }

        return null;
    }

    /**
     * Cache secret locally with TTL
     */
    private static function cacheSecret(string $cacheFile, array $secret): void
    {
        self::$cachedSecret = $secret;
        self::$cacheExpiry = time() + self::CACHE_TTL_SECONDS;
        $toCache = $secret;
        $toCache['_expires'] = time() + self::CACHE_TTL_SECONDS;
        @file_put_contents($cacheFile, json_encode($toCache), LOCK_EX);
        @chmod($cacheFile, 0600);
    }

    /**
     * Fetch secret on EC2 using IMDSv2 metadata credentials & AWS SigV4 request
     */
    private static function fetchViaEc2IamRole(string $secretName, string $region): ?array
    {
        // 1. Get IMDSv2 Token (1 second timeout so local dev does not hang)
        $ch = curl_init('http://169.254.169.254/latest/api/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['X-aws-ec2-metadata-token-ttl-seconds: 21600'],
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_TIMEOUT_MS => 1200,
            CURLOPT_CONNECTTIMEOUT_MS => 800,
        ]);
        $token = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($token)) {
            // Not running on EC2 or IMDS disabled
            return null;
        }

        // 2. Get IAM Role Name
        $ch = curl_init('http://169.254.169.254/latest/meta-data/iam/security-credentials/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["X-aws-ec2-metadata-token: {$token}"],
            CURLOPT_TIMEOUT => 2,
        ]);
        $roleName = trim(curl_exec($ch) ?: '');
        curl_close($ch);

        if (empty($roleName)) {
            return null;
        }

        // 3. Get IAM temporary credentials
        $ch = curl_init("http://169.254.169.254/latest/meta-data/iam/security-credentials/{$roleName}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["X-aws-ec2-metadata-token: {$token}"],
            CURLOPT_TIMEOUT => 2,
        ]);
        $credJson = curl_exec($ch);
        curl_close($ch);

        $creds = json_decode($credJson ?: '', true);
        if (!isset($creds['AccessKeyId'], $creds['SecretAccessKey'], $creds['Token'])) {
            return null;
        }

        // 4. Call Secrets Manager REST API with SigV4
        return self::callSecretsManagerApi($secretName, $region, $creds['AccessKeyId'], $creds['SecretAccessKey'], $creds['Token']);
    }

    /**
     * Execute AWS SigV4 signed POST to secretsmanager.<region>.amazonaws.com
     */
    private static function callSecretsManagerApi(string $secretId, string $region, string $accessKey, string $secretKey, string $sessionToken): ?array
    {
        $service = 'secretsmanager';
        $host = "secretsmanager.{$region}.amazonaws.com";
        $endpoint = "https://{$host}/";
        $payload = json_encode(['SecretId' => $secretId]);

        $dateTime = gmdate('Ymd\THis\Z');
        $dateOnly = gmdate('Ymd');

        $canonicalUri = '/';
        $canonicalQueryString = '';
        $canonicalHeaders = "content-type:application/x-amz-json-1.1\n" .
                            "host:{$host}\n" .
                            "x-amz-date:{$dateTime}\n" .
                            "x-amz-security-token:{$sessionToken}\n" .
                            "x-amz-target:secretsmanager.GetSecretValue\n";
        $signedHeaders = 'content-type;host;x-amz-date;x-amz-security-token;x-amz-target';
        $payloadHash = hash('sha256', $payload);

        $canonicalRequest = "POST\n{$canonicalUri}\n{$canonicalQueryString}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        $stringToSign = "AWS4-HMAC-SHA256\n{$dateTime}\n{$dateOnly}/{$region}/{$service}/aws4_request\n" . hash('sha256', $canonicalRequest);

        // Derive signing key
        $kSecret = 'AWS4' . $secretKey;
        $kDate = hash_hmac('sha256', $dateOnly, $kSecret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorizationHeader = "AWS4-HMAC-SHA256 Credential={$accessKey}/{$dateOnly}/{$region}/{$service}/aws4_request, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-amz-json-1.1',
                "Host: {$host}",
                "X-Amz-Date: {$dateTime}",
                "X-Amz-Security-Token: {$sessionToken}",
                'X-Amz-Target: secretsmanager.GetSecretValue',
                "Authorization: {$authorizationHeader}"
            ]
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['SecretString'])) {
                $decoded = json_decode($data['SecretString'], true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        } else {
            error_log("Secrets Manager API call failed: HTTP {$httpCode} - " . substr($response ?: '', 0, 200));
        }

        return null;
    }
}
