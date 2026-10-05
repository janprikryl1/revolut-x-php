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
