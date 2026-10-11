# User Guide: Market Data

Revolut X provides public endpoints for querying instrument configurations, price tickers, order book depth, and historical OHLCV candlesticks.

---

## 1. Trading Pairs & Configuration

```php
use RevolutX\Client;

$client = new Client();

// Retrieve all active trading pairs
$pairs = $client->getPairs();

// Retrieve configuration for a specific pair
$btcEur = $client->getPair('BTC-EUR');
echo "Min order size: " . $btcEur['min_order_size'] . " BTC\n";
echo "Price tick step: " . $btcEur['quote_step'] . " EUR\n";
```

---

## 2. Price Tickers

Tickers return the current best bid, best ask, and last traded price:

```php
// Ticker for a single trading pair
$ticker = $client->getTicker('BTC-EUR');
echo "Bid: {$ticker['bid']} EUR | Ask: {$ticker['ask']} EUR | Last: {$ticker['last_price']} EUR\n";

// Tickers for all active pairs
$allTickers = $client->getTickers();
```

---

## 3. Order Book Depth

```php
// Retrieve order book depth for BTC-EUR (top 10 levels)
$orderBook = $client->getOrderBook('BTC-EUR', depth: 10);

echo "Best Bid: " . $orderBook['bids'][0][0] . " EUR (Qty: " . $orderBook['bids'][0][1] . " BTC)\n";
echo "Best Ask: " . $orderBook['asks'][0][0] . " EUR (Qty: " . $orderBook['asks'][0][1] . " BTC)\n";
```

---

## 4. Candlesticks (OHLCV)

Supported candle intervals are defined in the `Interval` class:

| Constant | Value (minutes) | Description |
| :--- | :--- | :--- |
| `Interval::M1` | `1` | 1 minute |
| `Interval::M5` | `5` | 5 minutes |
| `Interval::M15` | `15` | 15 minutes |
| `Interval::H1` | `60` | 1 hour |
| `Interval::H4` | `240` | 4 hours |
| `Interval::D1` | `1440` | 1 day |

```php
use RevolutX\Types\Interval;

$candles = $client->getCandles('BTC-EUR', interval: Interval::H1);

foreach (array_slice($candles, -5) as $candle) {
    echo "Time: {$candle['start']} | Open: {$candle['open']} | Close: {$candle['close']} | Volume: {$candle['volume']}\n";
}
```

### Time handling (read this before building a "today" report)

!!! warning "Candle buckets are UTC-aligned, and the newest candle is still forming"
    These two details are the most common source of candle reports that disagree
    with each other even though the underlying data is identical:

    1. **`start`, `since` and `until` are Unix timestamps in _milliseconds_, and the
       buckets are aligned to UTC — not to your local timezone.** With
       `Interval::D1` the daily bucket opens at `00:00` UTC, which is `01:00`
       in `Europe/Prague` (CET) — so a "local calendar day" candle does not exist.
       Taking "today midnight" in UTC and taking it in a `UTC+2` zone selects two
       different sets of candles, both of them correct answers to different questions.
    2. **The most recent candle is not closed yet.** Its `close`, `high`, `low` and
       `volume` keep changing until the interval ends, so two runs minutes apart
       legitimately return different numbers for the same `start`.

Pass **both** `since` and `until` and the API returns only closed candles —
the still-forming one is left out, which is what you want for a stable report:

```php
use RevolutX\Types\Interval;

$interval = Interval::H1;

// Decide the timezone explicitly instead of relying on date_default_timezone_get().
$tz = new DateTimeZone('Europe/Prague');

$startOfDay = (new DateTimeImmutable('today midnight', $tz))->getTimestamp() * 1000;
$endOfDay   = (new DateTimeImmutable('tomorrow midnight', $tz))->getTimestamp() * 1000;

$candles = $client->getCandles(
    'BTC-EUR',
    interval: $interval,
    since: $startOfDay,
    until: $endOfDay
);

foreach ($candles as $candle) {
    $time = (new DateTimeImmutable('@' . intdiv((int) $candle['start'], 1000)))
        ->setTimezone($tz)
        ->format('Y-m-d H:i');

    echo "{$time} | O: {$candle['open']} | H: {$candle['high']} | L: {$candle['low']} | C: {$candle['close']}\n";
}
```

If you deliberately want the live, unfinished candle (for a ticker-style display),
pass `since` on its own — it is then included as the last element.

!!! warning "Do not filter a full response client-side for fine intervals"
    Called without `since`/`until`, the endpoint returns **up to 1000 candles**.
    That comfortably covers one day for `H1` or `D1`, but `1000 × 1m ≈ 16.7 hours`
    — so fetching `Interval::M1` without bounds and filtering for "today" in PHP
    silently loses the early-morning candles once the local time passes roughly
    `16:40`. Always bound the request with `since`/`until` instead.
