<?php

declare(strict_types=1);

namespace RevolutX\Market;

use DateTimeInterface;
use InvalidArgumentException;
use RevolutX\Helpers\SymbolNormalizer;
use RevolutX\Helpers\TimestampConverter;
use RevolutX\Types\Interval;

trait MarketTrait
{
    /**
     * Retrieve the configuration and trading limits for all active pairs.
     *
     * @return array<string, mixed>
     */
    public function getPairs(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->http->request('GET', '/public/configuration/pairs');
        return $data;
    }

    /**
     * Retrieve the configuration for a specific trading pair.
     *
     * @param string $symbol Trading pair (e.g. 'BTC-EUR', 'BTC/EUR', 'btceur').
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function getPair(string $symbol): array
    {
        $pairs = $this->getPairs();

        $cleanSlash = strtoupper(str_replace('-', '/', $symbol));
        $cleanDash = strtoupper(str_replace('/', '-', $symbol));

        if (isset($pairs[$cleanSlash])) {
            return $pairs[$cleanSlash];
        }
        if (isset($pairs[$cleanDash])) {
            return $pairs[$cleanDash];
        }

        foreach ($pairs as $k => $v) {
            $upperK = strtoupper((string)$k);
            if ($upperK === $cleanSlash || $upperK === $cleanDash) {
                return $v;
            }
        }

        $available = implode(', ', array_slice(array_keys($pairs), 0, 10));
        throw new InvalidArgumentException(
            "Trading pair '{$symbol}' not found on Revolut X. Available pairs include: {$available}..."
        );
    }

    /**
     * Retrieve the configuration for all supported currencies.
     *
     * @return array<string, mixed>
     */
    public function getCurrencies(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->http->request('GET', '/public/configuration/currencies');
        return $data;
    }

    /**
     * Order two quotes for the same symbol, most liquid first.
     *
     * 24-hour base volume decides; a missing or non-numeric volume ranks below
     * any usable one. The region name breaks an exact tie, keeping the choice
     * deterministic even when both venues report the same volume (notably 0 on
     * an untraded pair).
     *
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private static function compareLiquidity(array $a, array $b): int
    {
        $rawA = $a['volume_24h'] ?? null;
        $rawB = $b['volume_24h'] ?? null;
        $volumeA = is_numeric($rawA) ? (float)$rawA : -1.0;
        $volumeB = is_numeric($rawB) ? (float)$rawB : -1.0;

        return ($volumeB <=> $volumeA)
            ?: strcmp((string)($b['region'] ?? ''), (string)($a['region'] ?? ''));
    }

    /**
     * Retrieve current tickers (best bid, best ask, last price) for all pairs.
     *
     * Returns one entry per pair **and region**, not one per pair: most symbols
     * are quoted in both 'EEA' and 'UK' at different prices and volumes, and so
     * appear twice. Read ['region'] to tell them apart. The order of the list
     * is not stable between calls.
     *
     * @return list<array<string, mixed>>
     */
    public function getTickers(): array
    {
        $data = $this->http->request('GET', '/public/tickers');
        if (is_array($data) && isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }
        return is_array($data) ? $data : [];
    }

    /**
     * Retrieve the current ticker for a specific trading pair.
     *
     * Most symbols are quoted in more than one region ('EEA' and 'UK') at
     * different prices and volumes. Because /public/tickers returns them in an
     * order that changes between calls, picking the first match would hand back
     * a different region — and so a different price — on each call. This method
     * therefore returns the **most liquid** quote: the match with the highest
     * volume_24h, with the region name breaking an exact tie. To work with a
     * specific region, filter getTickers() on ['region'] yourself.
     *
     * @param string $symbol Trading pair (e.g. 'BTC-EUR').
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function getTicker(string $symbol): array
    {
        $tickers = $this->getTickers();

        $targetSlash = strtoupper(str_replace('-', '/', $symbol));
        $targetDash = strtoupper(str_replace('/', '-', $symbol));

        $matches = [];
        foreach ($tickers as $t) {
            if (is_array($t) && isset($t['symbol'])) {
                $sym = strtoupper((string)$t['symbol']);
                if ($sym === $targetSlash || $sym === $targetDash) {
                    $matches[] = $t;
                }
            }
        }

        if ($matches !== []) {
            usort($matches, static fn(array $a, array $b): int => self::compareLiquidity($a, $b));
            return $matches[0];
        }

        throw new InvalidArgumentException(
            "Ticker data not available for '{$symbol}'. Check that the pair exists with getPairs()."
        );
    }

    /**
     * Retrieve the current order book (bids and asks) for a trading pair.
     *
     * @param string $symbol Trading pair (e.g. 'BTC-EUR').
     * @param int $depth Number of price levels (default: 10).
     * @return array<string, mixed>
     */
    public function getOrderBook(string $symbol, int $depth = 10): array
    {
        $apiSymbol = SymbolNormalizer::normalize($symbol);
        $data = $this->http->request('GET', "/public/order-book/{$apiSymbol}", ['limit' => $depth]);
        if (is_array($data) && isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }
        return is_array($data) ? $data : [];
    }

    /**
     * Fetch up to 1000 OHLCV candlesticks for a trading pair.
     *
     * @param string $symbol Trading pair (e.g. 'BTC-EUR').
     * @param int $interval Interval in minutes (default: 60).
     * @param int|float|string|DateTimeInterface|null $since Start time — a Unix
     *        timestamp in **milliseconds**, a DateTimeInterface, or a date
     *        string (see {@see TimestampConverter::toMilliseconds()}).
     * @param int|float|string|DateTimeInterface|null $until End time, in the
     *        same formats as $since.
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException If a time bound cannot be converted.
     */
    public function getCandles(
        string $symbol,
        int $interval = Interval::H1,
        int|float|string|DateTimeInterface|null $since = null,
        int|float|string|DateTimeInterface|null $until = null
    ): array {
        $apiSymbol = SymbolNormalizer::normalize($symbol);
        $params = ['interval' => $interval];
        $sinceMs = TimestampConverter::toMillisecondsOrNull($since);
        $untilMs = TimestampConverter::toMillisecondsOrNull($until);
        if ($sinceMs !== null) {
            $params['since'] = $sinceMs;
        }
        if ($untilMs !== null) {
            $params['until'] = $untilMs;
        }

        $data = $this->http->request('GET', "/public/candles/{$apiSymbol}", $params);
        if (is_array($data) && isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }
        return is_array($data) ? $data : [];
    }

    /**
     * Fetch a single page of public trades (up to 100 per request).
     *
     * The symbol travels as a query parameter, not in the path: the venue
     * serves public trades from /public/trades/all and has no per-symbol
     * public path.
     *
     * @param string $symbol Trading pair (e.g. 'BTC-EUR').
     * @param int $limit Page size. The exchange accepts **1–100** and rejects
     *        anything larger with HTTP 400, so values outside that range are
     *        clamped into it. To read more than one page, follow next_cursor.
     * @param int|float|string|DateTimeInterface|null $startDate Start time — a
     *        Unix timestamp in **milliseconds**, a DateTimeInterface, or a date
     *        string (see {@see TimestampConverter::toMilliseconds()}).
     * @param int|float|string|DateTimeInterface|null $endDate End time, in the
     *        same formats as $startDate.
     * @param string|null $cursor Pagination cursor from a previous response's
     *        next_cursor.
     * @return array{trades: list<array<string, mixed>>, next_cursor: string|null}
     *
     * @throws InvalidArgumentException If a time bound cannot be converted.
     */
    public function getTrades(
        string $symbol,
        int $limit = 100,
        int|float|string|DateTimeInterface|null $startDate = null,
        int|float|string|DateTimeInterface|null $endDate = null,
        ?string $cursor = null
    ): array {
        $params = [
            'symbol' => SymbolNormalizer::normalize($symbol),
            // The venue rejects anything outside 1-100 with HTTP 400.
            'limit' => max(1, min($limit, 100)),
        ];

        $startMs = TimestampConverter::toMillisecondsOrNull($startDate);
        $endMs = TimestampConverter::toMillisecondsOrNull($endDate);
        if ($startMs !== null) {
            $params['start_date'] = $startMs;
        }
        if ($endMs !== null) {
            $params['end_date'] = $endMs;
        }
        if ($cursor !== null) {
            $params['cursor'] = $cursor;
        }

        $resp = $this->http->request('GET', '/public/trades/all', $params);

        $trades = [];
        $nextCursor = null;
        if (is_array($resp)) {
            $trades = isset($resp['data']) && is_array($resp['data']) ? $resp['data'] : [];
            $nextCursor = $resp['metadata']['next_cursor'] ?? null;
        }

        return [
            'trades' => $trades,
            'next_cursor' => $nextCursor,
        ];
    }
}
