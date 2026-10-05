# PHP SDK — Quick Start

The Revolut X PHP SDK provides complete integration with the Revolut X Crypto Exchange in modern PHP 8.0+.

---

## Requirements & Installation

### Requirements
- PHP `^8.0` (8.0.30+, 8.1, 8.2, 8.3, 8.4, 8.5)
- Standard PHP Extensions: `ext-curl`, `ext-json`, `ext-sodium`

### Installation via Composer

Install the library using Composer:

```bash
composer require janprikryl/revolutx
```

Or for local development:

```bash
git clone https://github.com/janprikryl1/revolut-x-php.git
cd revolut-x-php
composer install
```

---

## Client Initialization

### 1. Public Mode (No API Key Required)
For fetching market data, ticker prices, and order books, no authentication is needed:

```php
use RevolutX\Client;

$client = new Client();
$ticker = $client->getTicker('BTC-EUR');
echo "Current BTC Price: {$ticker['last_price']} EUR\n";
```

### 2. Authenticated Mode (Trading & Account Data)
For placing orders and inspecting balances, provide your API key and private key:

```php
use RevolutX\Client;

$client = new Client(
    apiKey: 'your-api-key',
    privateKeyPath: 'keys/private.pem'
);

$balances = $client->getBalances();
print_r($balances);
```

---

## Configuration Options

When creating an instance of `Client`, the following constructor parameters are available:

| Parameter | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `apiKey` | `?string` | `null` | Your Revolut X API key from account settings. |
| `privateKeyPath` | `?string` | `null` | Path to your Ed25519 private key file (`.pem`). |
| `privateKeyBytes` | `?string` | `null` | Raw PEM string or binary Ed25519 key bytes. |
| `baseUrl` | `string` | `'https://revx.revolut.com/api'` | API base URL. |
| `apiVersion` | `string` | `'1.0'` | Revolut X API version string. |
| `requestDelay` | `float` | `0.85` | Minimum delay in seconds between requests (rate-limit prevention). |
| `timeout` | `int` | `15` | cURL request timeout in seconds. |
| `maxRetries` | `int` | `3` | Maximum automatic retries on HTTP 429 and 5xx errors. |
