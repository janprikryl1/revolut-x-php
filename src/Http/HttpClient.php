<?php

declare(strict_types=1);

namespace RevolutX\Http;

use RevolutX\Auth\Signer;
use RevolutX\Exceptions\ApiException;
use RevolutX\Exceptions\AuthenticationException;
use RevolutX\Exceptions\NetworkException;
use RevolutX\Exceptions\RateLimitException;

/**
 * Robust HTTP client using native cURL with automated rate limiting,
 * request signing, retry logic, and error handling for Revolut X.
 */
class HttpClient
{
    private string $baseUrl;
    private string $apiVersion;
    private ?string $apiKey;
    private ?string $secretKey;
    private float $requestDelay;
    private int $timeout;
    private int $maxRetries;
    private float $maxRetryWait;
    private float $lastRequestTime = 0.0;
    private int $timestampOffsetMs;
    private bool $timeSynced = false;

    /**
     * Fallback wait in milliseconds when a 429 carries no 'retry-after'.
     */
    private const DEFAULT_RETRY_AFTER_MS = 2000.0;

    /**
     * @param float $maxRetryWait Hard ceiling in seconds for a single 429 wait,
     *        so one rate-limited call can never pin the process for minutes.
     */
    public function __construct(
        string $baseUrl = 'https://revx.revolut.com/api',
        string $apiVersion = '1.0',
        ?string $apiKey = null,
        ?string $secretKey = null,
        float $requestDelay = 0.85,
        int $timeout = 15,
        int $maxRetries = 3,
        float $maxRetryWait = 30.0,
        int $timestampOffsetMs = 0
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiVersion = trim($apiVersion, '/');
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->requestDelay = $requestDelay;
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
        $this->maxRetryWait = max($maxRetryWait, 1.0);
        $this->timestampOffsetMs = $timestampOffsetMs;
    }

    public function getTimestampOffset(): int
    {
        return $this->timestampOffsetMs;
    }

    public function setTimestampOffset(int $offsetMs): void
    {
        $this->timestampOffsetMs = $offsetMs;
    }

    /**
     * Synchronize timestamp offset with Revolut X server time using the Date response header.
     */
    public function syncTime(): int
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/public/configuration/currencies";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        $response = curl_exec($ch);
        if ($response !== false) {
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $rawHeaders = substr((string)$response, 0, $headerSize);
            if (preg_match('/^Date:\s*(.+)$/im', $rawHeaders, $matches)) {
                $serverTimeSec = strtotime(trim($matches[1]));
                if ($serverTimeSec !== false) {
                    $serverMs = $serverTimeSec * 1000;
                    $localMs = (int)(microtime(true) * 1000);
                    $this->timestampOffsetMs = $serverMs - $localMs - 500;
                    return $this->timestampOffsetMs;
                }
            }
        }
        unset($ch);

        return $this->timestampOffsetMs;
    }

    public function isAuthenticated(): bool
    {
        return $this->apiKey !== null && $this->secretKey !== null;
    }

    /**
     * Executes an HTTP request with automatic signing, rate limiting, and retries.
     *
     * @param string $method HTTP method (GET, POST, DELETE, etc.).
     * @param string $path Endpoint path (e.g. '/orders' or '/public/tickers').
     * @param array<string, mixed>|null $params Query parameters.
     * @param mixed $body Request body (null, array, or object).
     * @param bool $auth Whether request must be authenticated.
     * @return mixed Parsed JSON response.
     *
     * @throws AuthenticationException
     * @throws RateLimitException
     * @throws ApiException
     * @throws NetworkException
     */
    public function request(
        string $method,
        string $path,
        ?array $params = null,
        mixed $body = null,
        bool $auth = false
    ): mixed {
        $method = strtoupper($method);

        // Normalize endpoint with api version
        $endpoint = str_starts_with($path, '/') ? $path : '/' . $path;

        if (str_starts_with($endpoint, "/api/{$this->apiVersion}")) {
            $apiPath = $endpoint;
            $urlPath = substr($endpoint, 4);
        } elseif (str_starts_with($endpoint, "/{$this->apiVersion}")) {
            $apiPath = '/api' . $endpoint;
            $urlPath = $endpoint;
        } else {
            $apiPath = "/api/{$this->apiVersion}" . $endpoint;
            $urlPath = "/{$this->apiVersion}" . $endpoint;
        }

        // Build URL
        $url = $this->baseUrl . $urlPath;
        if ($params !== null && !empty($params)) {
            $filtered = [];
            foreach ($params as $k => $v) {
                if ($v !== null) {
                    $filtered[(string)$k] = (string)$v;
                }
            }
            if (!empty($filtered)) {
                $url .= '?' . http_build_query($filtered, '', '&', PHP_QUERY_RFC3986);
            }
        }

        if ($auth && !$this->timeSynced && $this->timestampOffsetMs === 0) {
            $this->timeSynced = true;
            $this->syncTime();
        }

        $baseHeaders = [
            'User-Agent: revolut-x-php/0.1.0',
            'Accept: application/json',
        ];

        if ($auth) {
            if (!$this->isAuthenticated()) {
                throw new AuthenticationException(
                    "Endpoint {$method} {$path} requires authentication, but apiKey or privateKey is missing.",
                    "Provide apiKey and privateKey when instantiating RevolutXClient."
                );
            }
        } elseif ($body !== null) {
            $baseHeaders[] = 'Content-Type: application/json';
        }

        $postData = null;
        if ($body !== null) {
            $postData = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $attempt = 0;
        while (true) {
            $attempt++;

            // Rate limiting delay
            $this->enforceRateLimit();

            $headers = $baseHeaders;
            if ($auth) {
                $authHeaders = Signer::signRequest(
                    apiKey: $this->apiKey,
                    secretKey: $this->secretKey,
                    method: $method,
                    path: $apiPath,
                    params: $params,
                    body: $body,
                    timestampOffsetMs: $this->timestampOffsetMs
                );

                foreach ($authHeaders as $headerKey => $headerVal) {
                    $headers[] = "{$headerKey}: {$headerVal}";
                }
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

            if ($postData !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            }

            $rawResult = curl_exec($ch);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            unset($ch);

            $this->lastRequestTime = microtime(true);

            if ($rawResult === false || $curlErrno !== 0) {
                if ($attempt <= $this->maxRetries) {
                    usleep(500000 * $attempt); // 0.5s, 1.0s, 1.5s
                    continue;
                }
                throw new NetworkException("cURL error ({$curlErrno}): {$curlError}");
            }

            $rawHeaders = substr((string)$rawResult, 0, $headerSize);
            $rawBody = substr((string)$rawResult, $headerSize);

            // Parse response Date header for clock drift detection
            if (preg_match('/^Date:\s*(.+)$/im', $rawHeaders, $matches)) {
                $serverTimeSec = strtotime(trim($matches[1]));
                if ($serverTimeSec !== false) {
                    $diff = ($serverTimeSec * 1000) - (int)(microtime(true) * 1000);
                    if (abs($diff - $this->timestampOffsetMs) > 3000) {
                        $this->timestampOffsetMs = $diff - 500;
                    }
                }
            }

            // Handle 429 Too Many Requests
            if ($httpCode === 429) {
                $waitSeconds = $this->retryAfterSeconds($rawHeaders);
                if ($attempt <= $this->maxRetries) {
                    usleep((int) ($waitSeconds * 1000000));
                    continue;
                }
                throw new RateLimitException(
                    "Rate limit exceeded after {$this->maxRetries} retries.",
                    (int) ceil($waitSeconds)
                );
            }

            // Parse response body
            $parsedBody = json_decode($rawBody, true);
            if ($parsedBody === null && json_last_error() !== JSON_ERROR_NONE && !empty($rawBody)) {
                $parsedBody = $rawBody;
            }

            // Handle 409 Conflict clock drift error
            if ($httpCode === 409 && is_array($parsedBody)) {
                $msg = strtolower((string)($parsedBody['message'] ?? $parsedBody['error'] ?? ''));
                if (str_contains($msg, 'future') || str_contains($msg, 'timestamp') || isset($parsedBody['timestamp'])) {
                    if (isset($parsedBody['timestamp']) && is_numeric($parsedBody['timestamp'])) {
                        $serverTs = (int)$parsedBody['timestamp'];
                        $this->timestampOffsetMs = $serverTs - (int)(microtime(true) * 1000) - 1000;
                    } else {
                        $this->syncTime();
                    }
                    if ($attempt <= $this->maxRetries) {
                        continue;
                    }
                }
            }

            // Handle 5xx server errors with retry
            if ($httpCode >= 500 && $attempt <= $this->maxRetries) {
                usleep(500000 * $attempt);
                continue;
            }

            // Check non-2xx codes
            if ($httpCode < 200 || $httpCode >= 300) {
                $this->handleHttpError($httpCode, $parsedBody);
            }

            return $parsedBody;
        }
    }

    private function enforceRateLimit(): void
    {
        if ($this->requestDelay > 0 && $this->lastRequestTime > 0) {
            $elapsed = microtime(true) - $this->lastRequestTime;
            if ($elapsed < $this->requestDelay) {
                $sleepUs = (int) (($this->requestDelay - $elapsed) * 1000000);
                if ($sleepUs > 0) {
                    usleep($sleepUs);
                }
            }
        }
    }

    /**
     * How long to wait after a 429, in seconds.
     *
     * Revolut X sends 'retry-after' in **milliseconds**, not in the seconds the
     * HTTP spec prescribes — observed values are 600–900 for a window that
     * clears in about a second.  Reading them as seconds turned a single 429
     * into a 14-minute `sleep()`, long enough for PHP to kill the request with
     * 'Maximum execution time exceeded' and, under FPM, to pin a worker.
     *
     * The result is floored at one second (the spacing the venue expects) and
     * capped at $maxRetryWait, so a server that one day starts sending
     * spec-compliant seconds cannot hang the caller either.
     */
    protected function retryAfterSeconds(string $headers): float
    {
        $milliseconds = $this->extractRetryAfter($headers) ?? self::DEFAULT_RETRY_AFTER_MS;

        return min(max($milliseconds / 1000.0, 1.0), $this->maxRetryWait);
    }

    private function extractRetryAfter(string $headers): ?float
    {
        if (preg_match('/^retry-after:\s*(\d+)/im', $headers, $matches)) {
            return (float) $matches[1];
        }
        return null;
    }

    /**
     * @throws AuthenticationException
     * @throws ApiException
     */
    private function handleHttpError(int $httpCode, mixed $body): never
    {
        $message = "Request failed with HTTP {$httpCode}";
        $errorCode = null;

        if (is_array($body)) {
            $message = $body['message'] ?? $body['error'] ?? $body['msg'] ?? $message;
            $errorCode = isset($body['code']) ? (string) $body['code'] : null;
        } elseif (is_string($body) && !empty($body)) {
            $message = $body;
        }

        if ($httpCode === 401 || $httpCode === 403) {
            throw new AuthenticationException(
                "Authentication rejected (HTTP {$httpCode}): {$message}",
                "Verify that your API key is active and your Ed25519 private key corresponds to the public key registered on Revolut X."
            );
        }

        throw new ApiException(
            message: (string)$message,
            statusCode: $httpCode,
            responseBody: $body,
            errorCode: $errorCode
        );
    }
}
