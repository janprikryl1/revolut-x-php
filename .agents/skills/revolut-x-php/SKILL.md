---
name: revolut-x-php
description: >-
  Use this skill when the user asks to interact with the Revolut X Crypto
  Exchange API using PHP, when they need to fetch market data (tickers,
  order book, candles, trades), place or manage orders, check account
  balances, or work with any part of the janprikryl/revolutx PHP SDK — including
  authentication setup (Ed25519), fee calculation, order payload building, or error handling.
---

# revolut-x-php SDK

PHP SDK for the **Revolut X Crypto Exchange** REST API (v1.0).

- **PHP ≥ 8.0** required (supports PHP 8.0 through 8.5)
- **Standard Extensions**: `ext-curl`, `ext-json`, `ext-sodium`
- **Composer Package**: `janprikryl/revolutx`
- **PHP Namespace**: `RevolutX`
- **Online Documentation**: [https://janprikryl1.github.io/revolut-x-php/](https://janprikryl1.github.io/revolut-x-php/)
- **Repository**: [https://github.com/janprikryl1/revolut-x-php](https://github.com/janprikryl1/revolut-x-php)
- **All numeric values from the API are strings** (e.g. `"76000.50"`) to avoid floating-point inaccuracies.

---

## Installation

```bash
composer require janprikryl/revolutx
```

Or autoload in development:
```php
require_once __DIR__ . '/vendor/autoload.php';
```

---

## Client Initialization (`RevolutX\Client`)

The single entry point for all API interactions:

```php
use RevolutX\Client;

// 1. Public data only (no API keys required)
$client = new Client();

// 2. Authenticated mode (trading & account management)
$client = new Client(
    apiKey: 'your_api_key_here',
    privateKeyPath: 'keys/private.pem'
);

// Alternative: provide raw private key string/bytes
$client = new Client(
    apiKey: 'your_api_key_here',
    privateKeyBytes: $rawPemBytes
);
```

### Constructor Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `apiKey` | `?string` | `null` | Revolut X API key (required for private endpoints) |
| `privateKeyPath` | `?string` | `null` | Path to Ed25519 PEM private key file |
| `privateKeyBytes` | `?string` | `null` | Raw PEM string or binary key bytes |
| `baseUrl` | `string` | `'https://revx.revolut.com/api'` | API base URL |
| `apiVersion` | `string` | `'1.0'` | Revolut X API version string |
| `requestDelay` | `float` | `0.85` | Delay in seconds between requests (rate limit prevention) |
| `timeout` | `int` | `15` | cURL timeout in seconds |
| `maxRetries` | `int` | `3` | Retries on HTTP 429 and network errors |

`$client->isAuthenticated(): bool` — Returns `true` if both API key and private key are configured.

---

## Authentication Setup (Ed25519)

Revolut X uses Ed25519 cryptographic signing for private endpoints.

```bash
# 1. Generate Ed25519 key pair with OpenSSL
openssl genpkey -algorithm ed25519 -out keys/private.pem
openssl pkey -in keys/private.pem -pubout -out keys/public.pem

# 2. Upload public.pem to Revolut X → Settings → API to generate your API key
```

The PHP SDK automatically constructs canonical messages, calculates timestamps, generates detached signatures via `sodium_crypto_sign_detached`, and attaches headers:
- `X-Revx-API-Key`
- `X-Revx-Timestamp`
- `X-Revx-Signature`

---

## Public Market Data (No Auth Required)

```php
use RevolutX\Client;
use RevolutX\Types\Interval;

$client = new Client();

// 1. Current ticker (best bid, best ask, last price)
$ticker = $client->getTicker('BTC-EUR');
echo "Last Price: {$ticker['last_price']} EUR\n";

// 2. All tickers
$allTickers = $client->getTickers();

// 3. Order book depth
$book = $client->getOrderBook('BTC-EUR', depth: 10);
// Returns ['bids' => [['76000.00', '0.50'], ...], 'asks' => [...]]

// 4. Candlesticks (OHLCV)
$candles = $client->getCandles('BTC-EUR', interval: Interval::HOUR_1);

// 5. Public recent trades
$trades = $client->getTrades('BTC-EUR', limit: 50);

// 6. Instrument pair configurations & rules
$pairs = $client->getPairs();
$btcRules = $client->getPair('BTC-EUR');
echo "Min order size: {$btcRules['min_order_size']}\n";
```

---

## Order Execution & Management (Auth Required)

### 1. Market Orders (Taker Fee: 0.09%)

Must specify **either** `baseSize` (e.g. BTC) or `quoteSize` (e.g. EUR):

```php
use RevolutX\Types\OrderSide;

// Buy 50.00 EUR worth of BTC at market price
$order = $client->placeMarketOrder(
    symbol: 'BTC-EUR',
    side: OrderSide::BUY,
    quoteSize: '50.00'
);
echo "Order ID: " . $order['venue_order_id'] . "\n";
```

### 2. Limit Orders

```php
use RevolutX\Types\OrderSide;
use RevolutX\Types\TimeInForce;

$order = $client->placeLimitOrder(
    symbol: 'BTC-EUR',
    side: OrderSide::BUY,
    price: '75000.00',
    quoteSize: '100.00',
    postOnly: true,              // Guarantees Maker order (0.00% fee)
    timeInForce: TimeInForce::GTC
);
```

### 3. Smart Maker Orders (Guaranteed 0.00% Fee)

Automatically evaluates current market depth, computes an optimal limit price with safety offset, and submits with `post_only = true`:

```php
use RevolutX\Types\OrderSide;

$order = $client->placeMakerOrder(
    symbol: 'BTC-EUR',
    side: OrderSide::BUY,
    quoteSize: '50.00',
    offset: '0.10',              // Offset from best bid (default: 0.10)
    tickSize: '0.01'             // Quantization step
);
```

### 4. Querying & Canceling Orders

```php
// Check order status and total filled size
$detail = $client->getOrder('order-uuid-here');
echo "Status: {$detail['status']}\n";

// Inspect individual partial executions (fills)
$fills = $client->getOrderFills('order-uuid-here');

// Active open orders
$openOrders = $client->getActiveOrders('BTC-EUR');

// Historical completed orders (cursor pagination)
$history = $client->getHistoricalOrders('BTC-EUR', limit: 50);
$orders = $history['orders'];
$nextCursor = $history['next_cursor'];

// Cancel specific order
$client->cancelOrder('order-uuid-here');

// Cancel all open orders for symbol
$client->cancelAllOrders('BTC-EUR');
```

---

## Account & Wallet Management (Auth Required)

```php
// 1. All balances
$balances = $client->getBalances();

// 2. Specific currency balance
$eur = $client->getBalance('EUR');
echo "Available EUR: {$eur['available']}\n";

// 3. Transaction ledger history (deposits, withdrawals, fees)
$ledger = $client->getTransactions(limit: 20);

// 4. Private executed trade history with fee breakdowns
$myTrades = $client->getAccountTrades('BTC-EUR', limit: 50);
```

---

## Helpers & Calculators

### 1. `MakerOrderStrategy`
```php
use RevolutX\Helpers\MakerOrderStrategy;
use RevolutX\Types\OrderSide;

// BUY: best_bid - offset | SELL: best_ask + offset
$price = MakerOrderStrategy::calculateMakerPrice(
    side: OrderSide::BUY,
    bestBid: '76200.00',
    offset: '0.10',
    tickSize: '0.01'
);
// Output: "76199.90"
```

### 2. `FeeCalculator`
```php
use RevolutX\Helpers\FeeCalculator;
use RevolutX\Types\OrderSide;

$estimate = FeeCalculator::calculate(
    side: OrderSide::BUY,
    price: '76000.00',
    isMaker: true,               // true = 0.00%, false = 0.09%
    quoteSize: '1000.00'
);

echo $estimate->feeAmount;       // "0.0000"
echo $estimate->explanation;
```

---

## Exception Handling

All exceptions extend `RevolutX\Exceptions\RevolutXException`:

```php
use RevolutX\Exceptions\AuthenticationException;
use RevolutX\Exceptions\RateLimitException;
use RevolutX\Exceptions\ApiException;
use RevolutX\Exceptions\OrderValidationException;
use RevolutX\Exceptions\NetworkException;
use RevolutX\Exceptions\RevolutXException;

try {
    $client->placeMarketOrder('BTC-EUR', 'buy', quoteSize: '50.00');
} catch (AuthenticationException $e) {
    // Bad API key, invalid PEM key, or HTTP 401/403
    echo "Auth error: " . $e->getMessage() . "\nHint: " . $e->getHint();
} catch (RateLimitException $e) {
    // HTTP 429 Too Many Requests
    echo "Rate limit reached. Retry after: " . $e->getRetryAfterSeconds() . "s";
} catch (ApiException $e) {
    // Non-2xx response from Revolut X
    echo "API error (" . $e->getStatusCode() . "): " . $e->getMessage();
} catch (OrderValidationException $e) {
    // Pre-flight validation failure (e.g. missing sizes, below min order)
    echo "Validation error: " . $e->getMessage();
} catch (NetworkException $e) {
    // Timeout or DNS failure
    echo "Connection error: " . $e->getMessage();
} catch (RevolutXException $e) {
    // Generic catch-all
    echo "Revolut X error: " . $e->getMessage();
}
```

---

## Types Reference

See detailed type structures in [references/types.md](references/types.md):
- `RevolutX\Types\OrderSide`: `BUY = 'buy'`, `SELL = 'sell'`
- `RevolutX\Types\OrderType`: `MARKET = 'market'`, `LIMIT = 'limit'`
- `RevolutX\Types\TimeInForce`: `GTC = 'gtc'`, `IOC = 'ioc'`, `FOK = 'fok'`
- `RevolutX\Types\Interval`: `M1 = 1`, `M5 = 5`, `M15 = 15`, `H1 = 60`, `H4 = 240`, `D1 = 1440`
