# Revolut X PHP SDK

PHP client library for interacting with the **Revolut X Crypto Exchange REST API (v1.0)**.

📖 **Documentation**: [janprikryl1.github.io/revolut-x-php](https://janprikryl1.github.io/revolut-x-php/)  
🐍 **Looking for Python?** See [revolut-x-python](https://github.com/janprikryl1/revolut-x-python) or [Python Documentation](https://janprikryl1.github.io/revolut-x-python/).

---

## Features

- **Revolut X API v1.0** — Complete coverage of market data, order execution, account balances, and ledger transaction history.
- **Ed25519 Authentication** — Asymmetric request signing using PHP's native `ext-sodium` extension.
- **Zero External Runtime Dependencies** — Pure PHP utilizing built-in extensions (`ext-curl`, `ext-sodium`, `ext-json`).
- **PHP 8.0+ Compatible** — Tested across PHP 8.0, 8.1, 8.2, 8.3, 8.4, and 8.5 with strict types.
- **Smart Maker Strategy (0.00% fee)** — Automatic offset pricing and `post_only` execution to prevent unintentional taker fees (0.09%).
- **Automated Rate Limiting** — Proactive request throttling adhering to Revolut X limits (1 req/s) with exponential retry backoff on HTTP 429 (`Retry-After`).
- **Structured Exceptions** — Clean exception hierarchy (`AuthenticationException`, `RateLimitException`, `ApiException`, `OrderValidationException`).

---

## Requirements

- PHP `^8.0` (8.0.30+, 8.1, 8.2, 8.3, 8.4, 8.5)
- Extensions: `ext-curl`, `ext-json`, `ext-sodium`

---

## Installation

```bash
composer require janprikryl/revolutx
```

Or clone and install dependencies:

```bash
git clone https://github.com/janprikryl1/revolut-x-php.git
cd revolut-x-php
composer install
```

---

## Quickstart

### 1. Public Market Data (No API Key Required)

```php
use RevolutX\Client;
use RevolutX\Types\Interval;

$client = new Client();

// 1. Get current BTC-EUR ticker
$ticker = $client->getTicker('BTC-EUR');
echo "BTC Price: " . $ticker['last_price'] . " EUR\n";

// 2. Fetch order book (top 5 bids and asks)
$orderBook = $client->getOrderBook('BTC-EUR', depth: 5);

// 3. Fetch 1-hour OHLCV candles
$candles = $client->getCandles('BTC-EUR', Interval::HOUR_1);
```

### 2. Authenticated Trading & Balances

```php
use RevolutX\Client;
use RevolutX\Types\OrderSide;

$client = new Client(
    apiKey: 'your-api-key',
    privateKeyPath: 'path/to/private.pem'
);

// Check account balance
$eur = $client->getBalance('EUR');
echo "EUR Available: " . $eur['available'] . "\n";

// Place a market buy order for 50 EUR
$order = $client->placeMarketOrder(
    symbol: 'BTC-EUR',
    side: OrderSide::BUY,
    quoteSize: '50.00'
);
echo "Order ID: " . $order['venue_order_id'] . "\n";
```

### 3. Smart Maker Orders (0.00% Fee)

```php
use RevolutX\Client;
use RevolutX\Types\OrderSide;

// Automatically queries the live order book, applies offset, and submits post_only=true
$order = $client->placeMakerOrder(
    symbol: 'BTC-EUR',
    side: OrderSide::BUY,
    quoteSize: '50.00',
    offset: '0.10'
);
```

---

## Running Tests

```bash
./vendor/bin/phpunit
```

---

## Academic Reference

Tato knihovna vznikla jako součást **diplomové práce** na **VŠB – Technické univerzitě Ostrava** (Fakulta elektrotechniky a informatiky).

- **Autor**: Bc. Jan Přikryl
- **Univerzita**: VŠB – Technická univerzita Ostrava
- **Fakulta**: Fakulta elektrotechniky a informatiky (FEI)

---

## License

MIT License - see [LICENSE](LICENSE) for details.
