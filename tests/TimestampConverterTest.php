<?php

declare(strict_types=1);

namespace RevolutX\Tests;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RevolutX\Client;
use RevolutX\Helpers\TimestampConverter;
use RevolutX\Http\HttpClient;
use RevolutX\Types\Interval;

class TimestampConverterTest extends TestCase
{
    /** 2024-01-01T00:00:00Z */
    private const EPOCH_2024_MS = 1704067200000;

    public function testIntegersArePassedThroughAsMilliseconds(): void
    {
        // Seconds must not be auto-detected: guessing the unit from the
        // magnitude would silently change the meaning of the input.
        $this->assertSame(self::EPOCH_2024_MS, TimestampConverter::toMilliseconds(self::EPOCH_2024_MS));
        $this->assertSame(1704067200, TimestampConverter::toMilliseconds(1704067200));
        $this->assertSame(0, TimestampConverter::toMilliseconds(0));
    }

    public function testFloatsAreTruncated(): void
    {
        $this->assertSame(self::EPOCH_2024_MS, TimestampConverter::toMilliseconds(1704067200000.9));
    }

    public function testDateTimeIsConvertedUsingItsOwnTimezone(): void
    {
        $utc = new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC'));
        $this->assertSame(self::EPOCH_2024_MS, TimestampConverter::toMilliseconds($utc));

        // 02:00 in +02:00 is the same instant as midnight UTC.
        $prague = new DateTimeImmutable('2024-01-01 02:00:00', new DateTimeZone('+02:00'));
        $this->assertSame(self::EPOCH_2024_MS, TimestampConverter::toMilliseconds($prague));

        // The mutable sibling implements the same interface.
        $mutable = new DateTime('2024-01-01 00:00:00', new DateTimeZone('UTC'));
        $this->assertSame(self::EPOCH_2024_MS, TimestampConverter::toMilliseconds($mutable));
    }

    public function testSubSecondPrecisionIsKept(): void
    {
        $value = new DateTimeImmutable('2024-01-01 00:00:00.500', new DateTimeZone('UTC'));
        $this->assertSame(self::EPOCH_2024_MS + 500, TimestampConverter::toMilliseconds($value));
    }

    public function testInstantsBeforeTheEpochStayNegative(): void
    {
        $value = new DateTimeImmutable('1969-12-31 23:59:59.500', new DateTimeZone('UTC'));
        $this->assertSame(-500, TimestampConverter::toMilliseconds($value));
    }

    public function testStringsAreParsedAsUtcWhenNoOffsetIsGiven(): void
    {
        // Pin the process to a non-UTC zone: a naive string must not drift.
        $previous = date_default_timezone_get();
        date_default_timezone_set('Europe/Prague');

        try {
            $this->assertSame(self::EPOCH_2024_MS, TimestampConverter::toMilliseconds('2024-01-01'));
            $this->assertSame(
                self::EPOCH_2024_MS,
                TimestampConverter::toMilliseconds('2024-01-01T00:00:00')
            );
            $this->assertSame(
                self::EPOCH_2024_MS,
                TimestampConverter::toMilliseconds('2024-01-01T00:00:00Z')
            );
            $this->assertSame(
                self::EPOCH_2024_MS,
                TimestampConverter::toMilliseconds('2024-01-01T02:00:00+02:00')
            );
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function testLongDigitsOnlyStringsAreTreatedAsMilliseconds(): void
    {
        $this->assertSame(self::EPOCH_2024_MS, TimestampConverter::toMilliseconds('1704067200000'));
        $this->assertSame(-self::EPOCH_2024_MS, TimestampConverter::toMilliseconds('-1704067200000'));
        // Exactly at the threshold: 1973-03-03, the oldest value still read as ms.
        $this->assertSame(100000000000, TimestampConverter::toMilliseconds('100000000000'));
    }

    /**
     * @dataProvider separatorlessDateProvider
     */
    public function testShortDigitsOnlyStringsAreRejectedInsteadOfLandingIn1970(
        string $input
    ): void {
        // '20240101' used to be read as 20240101 ms (1970-01-01 05:37) while the
        // Python SDK parsed the same string as 2024-01-01 — a silent, wrong,
        // cross-SDK discrepancy. Both now refuse it.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ambiguous numeric timestamp string');

        TimestampConverter::toMilliseconds($input);
    }

    /**
     * @return array<string, array{string}>
     */
    public function separatorlessDateProvider(): array
    {
        return [
            'bare year' => ['2024'],
            'compact date' => ['20240101'],
            'zero' => ['0'],
            'seconds' => ['1704067200'],
            'signed' => ['-500'],
        ];
    }

    public function testEmptyStringIsRejectedRatherThanReadAsNow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be empty');

        TimestampConverter::toMilliseconds('   ');
    }

    public function testUnparsableStringIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unparsable timestamp string');

        TimestampConverter::toMilliseconds('not a date');
    }

    public function testNullIsOnlyAcceptedByTheNullableVariant(): void
    {
        $this->assertNull(TimestampConverter::toMillisecondsOrNull(null));
        $this->assertSame(
            self::EPOCH_2024_MS,
            TimestampConverter::toMillisecondsOrNull('2024-01-01T00:00:00Z')
        );
    }

    public function testGetCandlesSendsConvertedMillisecondBounds(): void
    {
        /** @var array<string, mixed> $params */
        $params = [];

        $http = $this->createMock(HttpClient::class);
        $http->method('request')->willReturnCallback(
            function (string $method, string $path, ?array $requestParams = null) use (&$params) {
                $params = $requestParams ?? [];
                return ['data' => []];
            }
        );

        $client = new class () extends Client {
            public function replaceHttp(HttpClient $http): void
            {
                $this->http = $http;
            }
        };
        $client->replaceHttp($http);

        $client->getCandles(
            'BTC-EUR',
            Interval::H1,
            new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC')),
            '2024-01-02T00:00:00Z'
        );

        $this->assertSame(self::EPOCH_2024_MS, $params['since']);
        $this->assertSame(self::EPOCH_2024_MS + 86400000, $params['until']);
        $this->assertIsInt($params['since']);
        $this->assertIsInt($params['until']);
    }
}
