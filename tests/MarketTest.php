<?php

declare(strict_types=1);

namespace RevolutX\Tests;

use InvalidArgumentException;
use RevolutX\Market\MarketTrait;
use PHPUnit\Framework\TestCase;

/**
 * Client whose HTTP layer replays one canned response.
 *
 * MarketTrait only ever reaches for $this->http->request(), so a stub with
 * that single method is enough to exercise it without a network call.
 */
class StubMarketClient
{
    use MarketTrait;

    /** @var object */
    protected $http;

    /** @param array<string, mixed> $response */
    public function __construct(array $response)
    {
        $this->http = new class ($response) {
            /** @var list<array{0: string, 1: string, 2: array<string, mixed>}> */
            public array $calls = [];

            /** @param array<string, mixed> $response */
            public function __construct(private array $response)
            {
            }

            /**
             * @param array<string, mixed> $params
             * @return array<string, mixed>
             */
            public function request(string $method, string $endpoint, array $params = []): array
            {
                $this->calls[] = [$method, $endpoint, $params];
                return $this->response;
            }
        };
    }

    /** @return array{0: string, 1: string, 2: array<string, mixed>} */
    public function lastCall(): array
    {
        return $this->http->calls[count($this->http->calls) - 1];
    }
}

class MarketTest extends TestCase
{
    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function tickerFrom(array $rows, string $symbol): array
    {
        return (new StubMarketClient(['data' => $rows]))->getTicker($symbol);
    }

    public function testGetTickerMatchesSlashAndDashSymbols(): void
    {
        $btc = ['symbol' => 'BTC/EUR', 'bid' => '73875.00', 'ask' => '73895.00',
                'last_price' => '73875', 'volume_24h' => '38.51'];
        $rows = [['symbol' => 'ETH/EUR'], $btc];

        foreach (['BTC-EUR', 'BTC/EUR', 'btc-eur'] as $requested) {
            $this->assertSame($btc, $this->tickerFrom($rows, $requested));
        }
    }

    /**
     * A symbol quoted in two regions must not resolve by list position.
     *
     * Regression test: /public/tickers returns one row per pair *and* region
     * and reorders them between calls, so returning the first match handed
     * back a different region — and a different price — on each call. The
     * liquid venue must win from either position.
     */
    public function testGetTickerIsIndependentOfResponseOrder(): void
    {
        $eea = ['symbol' => 'RAD/USD', 'region' => 'EEA',
                'last_price' => '0.3166', 'volume_24h' => '63402.95'];
        $uk = ['symbol' => 'RAD/USD', 'region' => 'UK',
               'last_price' => '0.3193', 'volume_24h' => '1014.82'];

        $this->assertSame($eea, $this->tickerFrom([$eea, $uk], 'RAD-USD'));
        $this->assertSame($eea, $this->tickerFrom([$uk, $eea], 'RAD-USD'));
    }

    public function testGetTickerPrefersAQuoteWithAUsableVolume(): void
    {
        $traded = ['symbol' => 'X/USD', 'region' => 'UK', 'volume_24h' => '0'];
        $noVolume = ['symbol' => 'X/USD', 'region' => 'EEA'];

        $this->assertSame($traded, $this->tickerFrom([$traded, $noVolume], 'X-USD'));
        $this->assertSame($traded, $this->tickerFrom([$noVolume, $traded], 'X-USD'));
    }

    public function testGetTickerBreaksAnExactTieDeterministically(): void
    {
        $eea = ['symbol' => 'Y/USD', 'region' => 'EEA', 'volume_24h' => '5'];
        $uk = ['symbol' => 'Y/USD', 'region' => 'UK', 'volume_24h' => '5'];

        $this->assertSame(
            $this->tickerFrom([$eea, $uk], 'Y-USD'),
            $this->tickerFrom([$uk, $eea], 'Y-USD')
        );
    }

    /**
     * Regression test: getTrades() used to build "/public/trades/{symbol}",
     * which is not a public path — the venue answered every single call with
     * {"message":"Unauthenticated access","code":2000}. The symbol belongs in
     * the query string of /public/trades/all.
     */
    public function testGetTradesCallsThePublicTradesEndpoint(): void
    {
        $client = new StubMarketClient(['data' => [], 'metadata' => []]);
        $client->getTrades('btc/eur');

        [$method, $endpoint, $params] = $client->lastCall();

        $this->assertSame('GET', $method);
        $this->assertSame('/public/trades/all', $endpoint);
        $this->assertSame('BTC-EUR', $params['symbol']);
    }

    /** The venue rejects limit > 100 with HTTP 400, so it must be clamped. */
    public function testGetTradesLimitStaysWithinTheApiBound(): void
    {
        foreach ([[100, 100], [1900, 100], [0, 1], [50, 50]] as [$asked, $expected]) {
            $client = new StubMarketClient(['data' => []]);
            $client->getTrades('BTC-EUR', $asked);
            $this->assertSame($expected, $client->lastCall()[2]['limit']);
        }
    }

    public function testGetTradesSendsTimeBoundsAsMilliseconds(): void
    {
        $client = new StubMarketClient(['data' => []]);
        $client->getTrades('BTC-EUR', 100, '2024-01-01T00:00:00Z', '2024-01-02T00:00:00Z');

        $params = $client->lastCall()[2];

        $this->assertSame(1704067200000, $params['start_date']);
        $this->assertSame(1704153600000, $params['end_date']);
    }

    public function testGetTradesOmitsUnsetOptionalParameters(): void
    {
        $client = new StubMarketClient(['data' => []]);
        $client->getTrades('BTC-EUR');

        $params = $client->lastCall()[2];

        $this->assertArrayNotHasKey('start_date', $params);
        $this->assertArrayNotHasKey('end_date', $params);
        $this->assertArrayNotHasKey('cursor', $params);
    }

    public function testGetTradesReturnsTradesWithTheNextCursor(): void
    {
        $rows = [['id' => 'a', 'price' => '74594.47'], ['id' => 'b', 'price' => '74590.00']];
        $client = new StubMarketClient([
            'data' => $rows,
            'metadata' => ['next_cursor' => 'abc123'],
        ]);

        $page = $client->getTrades('BTC-EUR');

        $this->assertSame($rows, $page['trades']);
        $this->assertSame('abc123', $page['next_cursor']);

        $client->getTrades('BTC-EUR', 100, null, null, 'abc123');
        $this->assertSame('abc123', $client->lastCall()[2]['cursor']);
    }

    public function testGetTradesReportsNoNextCursorOnTheLastPage(): void
    {
        $client = new StubMarketClient(['data' => [], 'metadata' => []]);

        $page = $client->getTrades('BTC-EUR');

        $this->assertSame([], $page['trades']);
        $this->assertNull($page['next_cursor']);
    }

    public function testGetTickerThrowsForAnUnknownSymbol(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->tickerFrom([['symbol' => 'BTC/EUR']], 'DOGE-EUR');
    }

    /**
     * Volumes arrive as strings and must be compared numerically: sorted as
     * text, '9' would outrank '63402.95'.
     */
    public function testGetTickerComparesVolumesNumericallyNotAsText(): void
    {
        $small = ['symbol' => 'Z/USD', 'region' => 'UK', 'volume_24h' => '9'];
        $large = ['symbol' => 'Z/USD', 'region' => 'EEA', 'volume_24h' => '63402.95'];

        $this->assertSame($large, $this->tickerFrom([$small, $large], 'Z-USD'));
        $this->assertSame($large, $this->tickerFrom([$large, $small], 'Z-USD'));
    }
}
