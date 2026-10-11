<?php

declare(strict_types=1);

namespace RevolutX\Tests;

use PHPUnit\Framework\TestCase;
use RevolutX\Http\HttpClient;

/**
 * Regression tests for the 429 backoff.
 *
 * Revolut X sends 'retry-after' in milliseconds, not seconds. Reading it as
 * seconds made a single rate-limited call sleep for a quarter of an hour:
 *
 *     PHP Fatal error: Maximum execution time of 30 seconds exceeded
 *       #0 src/Http/HttpClient.php(180): sleep(676)
 *
 * The wait is checked directly rather than by provoking a real 429, so the
 * suite stays offline and fast.
 */
class RetryAfterTest extends TestCase
{
    private function waitFor(string $headers, float $maxRetryWait = 30.0): float
    {
        // A subclass rather than reflection: ReflectionMethod::setAccessible()
        // is required on PHP 8.0, the lowest version this package supports, but
        // deprecated from 8.5 — this works on both.
        $client = new class (maxRetryWait: $maxRetryWait) extends HttpClient {
            public function waitSeconds(string $headers): float
            {
                return $this->retryAfterSeconds($headers);
            }
        };

        return $client->waitSeconds($headers);
    }

    public function testHeaderIsReadAsMillisecondsNotSeconds(): void
    {
        // Values actually observed from the venue: 676, 681, 836, 862.
        $headers = "HTTP/1.1 429 Too Many Requests\r\nretry-after: 836\r\n\r\n";

        // 0.836s, floored to the 1s spacing the venue expects — not 836s.
        $this->assertSame(1.0, $this->waitFor($headers));
    }

    public function testWaitIsFlooredAtOneSecond(): void
    {
        $headers = "HTTP/1.1 429 Too Many Requests\r\nretry-after: 1\r\n\r\n";

        $this->assertSame(1.0, $this->waitFor($headers));
    }

    public function testLongerWindowsAreHonouredInSeconds(): void
    {
        // 4500 ms — a genuinely longer cool-off, not clamped away.
        $headers = "HTTP/1.1 429 Too Many Requests\r\nretry-after: 4500\r\n\r\n";

        $this->assertSame(4.5, $this->waitFor($headers));
    }

    public function testWaitIsCappedSoOneCallCannotPinTheProcess(): void
    {
        // If the venue ever switched to spec-compliant seconds, '900' would
        // mean 15 minutes. The ceiling has to hold regardless.
        $headers = "HTTP/1.1 429 Too Many Requests\r\nretry-after: 900000\r\n\r\n";

        $this->assertSame(30.0, $this->waitFor($headers));
        $this->assertSame(2.5, $this->waitFor($headers, maxRetryWait: 2.5));
    }

    public function testMissingHeaderFallsBackToTwoSeconds(): void
    {
        $headers = "HTTP/1.1 429 Too Many Requests\r\ncontent-type: application/json\r\n\r\n";

        $this->assertSame(2.0, $this->waitFor($headers));
    }

    public function testHeaderMatchingIsCaseInsensitive(): void
    {
        $headers = "HTTP/1.1 429 Too Many Requests\r\nRetry-After: 4500\r\n\r\n";

        $this->assertSame(4.5, $this->waitFor($headers));
    }

    public function testCeilingCannotBeConfiguredBelowOneSecond(): void
    {
        $headers = "HTTP/1.1 429 Too Many Requests\r\nretry-after: 4500\r\n\r\n";

        // maxRetryWait: 0 would otherwise mean a hot retry loop.
        $this->assertSame(1.0, $this->waitFor($headers, maxRetryWait: 0.0));
    }
}
