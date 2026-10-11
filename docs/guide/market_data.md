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

### Bounding the time window

`$since` and `$until` accept any of these:

| Value | Meaning |
| :--- | :--- |
| `1700000000000` | Unix timestamp in **milliseconds**, passed through as-is. |
| `new DateTimeImmutable('2024-01-01', new DateTimeZone('UTC'))` | Converted using the object's own timezone. |
| `'2024-01-01T00:00:00Z'` | Date string — parsed, offsets honoured. |
| `'-7 days'` | Relative expression, as understood by `DateTimeImmutable`. |
| `'1700000000000'` | A digits-only string of 12+ digits is read as milliseconds. |

```php
use RevolutX\Types\Interval;

$candles = $client->getCandles(
    'BTC-EUR',
    interval: Interval::H1,
    since: new DateTimeImmutable('-7 days', new DateTimeZone('UTC')),
    until: new DateTimeImmutable('now', new DateTimeZone('UTC')),
);
```

A string without an explicit offset is interpreted as **UTC**, not as the
server's `date.timezone` — the same code therefore selects the same window on
every machine. An unparsable or empty string throws `InvalidArgumentException`.

!!! warning "Numbers are milliseconds, not seconds"
    Raw numbers are never rescaled, so `since: time()` is not an error — it is
    a window starting in January 1970, and you get back an empty or useless
    range. Use `time() * 1000`, or pass a `DateTimeImmutable` and let the SDK
    convert it. Response fields are milliseconds too: `$candle['start']` needs
    `/ 1000` before `date()`.

    The same conversion is available on its own via
    `RevolutX\Helpers\TimestampConverter::toMilliseconds()`.

### Values arriving from a request

Convert user input yourself instead of forwarding the raw string — the parser
here is a convenience, not an input validation layer:

```php
use RevolutX\Types\Interval;

$userTz = new DateTimeZone('Europe/Prague');
$raw = trim($_GET['from'] ?? '');          // e.g. '2024-01-01' from <input type="date">

if ($raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) !== 1) {
    http_response_code(400);
    exit(json_encode(['error' => 'from must be YYYY-MM-DD']));
}

// Midnight in the *user's* zone — not UTC midnight.
$since = $raw === '' ? null : new DateTimeImmutable("{$raw} 00:00:00", $userTz);

$candles = $client->getCandles('BTC-EUR', Interval::H1, since: $since);
```

Four things that bite when the value comes straight from a browser:

- **A string without an offset is UTC.** `'2024-01-01'` is UTC midnight, but a
  user in Prague who picked 1 January means 23:00 on 31 December UTC. Build the
  bound in their timezone and pass the `DateTimeImmutable`, as above. From
  JavaScript, `d.getTime()` (milliseconds) or `d.toISOString()` (carries `Z`)
  are both unambiguous; `'2024-01-01T13:30'` from `<input type="datetime-local">`
  is **not** — it has no offset, so it is read as UTC.
- **Slash dates are American.** `DateTimeImmutable` reads `'01/02/2024'` as
  2 January, so a European user who meant 1 February gets the wrong day with no
  error at all. Insist on `YYYY-MM-DD`.
- **An empty field is a string, not a missing one.** HTML forms submit `?from=`
  for untouched inputs, and `''` throws `InvalidArgumentException` — hence the
  `=== ''` checks above.
- **Separatorless dates are refused, not guessed.** `'2024'` and `'20240101'`
  throw rather than resolving to 1970, because a short run of digits is
  ambiguous between a date and a timestamp. Only 12+ digits count as
  milliseconds.

Relative expressions like `'-7 days'` or `'now'` are accepted too, which is
another reason to pin the accepted shape at the edge rather than letting
arbitrary request strings through.
