# Revolut X PHP SDK
<em>PHP client library for the Revolut X Crypto Exchange REST API (v1.0).</em>

---

!!! tip "Multi-Language Ecosystem"
    Looking for the **Python SDK**? Visit [Revolut X Python Documentation](https://janprikryl1.github.io/revolut-x-python/) or the [Python GitHub Repository](https://github.com/janprikryl1/revolut-x-python).

---

## Overview

This library provides a complete, strictly typed PHP 8.0+ integration for the **Revolut X Crypto Exchange REST API (v1.0)**. It is built for maximum performance with zero external runtime dependencies, native Ed25519 cryptographic signing via `ext-sodium`, automated exchange rate-limit management, and smart order execution guaranteeing **0.00% Maker fees**.

Developed as part of a Master's Thesis at **VŠB – Technical University of Ostrava** (Faculty of Electrical Engineering and Computer Science).

- **Author**: Bc. Jan Přikryl
- **Package**: [`janprikryl/revolutx`](https://packagist.org/packages/janprikryl/revolutx)
- **Repository**: [github.com/janprikryl1/revolut-x-php](https://github.com/janprikryl1/revolut-x-php)
- **Sister SDK (Python)**: [github.com/janprikryl1/revolut-x-python](https://github.com/janprikryl1/revolut-x-python)

---

## Key Highlights

- **Revolut X API v1.0 Compliance** — Full endpoint coverage: public market data, order books, OHLCV candles, order management, balances, and transaction history.
- **Native Ed25519 Cryptography** — Fast and secure asymmetric request signing using PHP's built-in `ext-sodium`.
- **Zero External Runtime Dependencies** — Uses only standard PHP extensions (`ext-curl`, `ext-json`, `ext-sodium`).
- **PHP 8.0+ Compatibility** — Tested across PHP 8.0 through 8.5 with `declare(strict_types=1);` and class-based enums.
- **Smart Maker Strategy (0.00% fee)** — Automatic price calculation with configurable safety offsets ensuring limit orders enter the order book as Makers (saving 0.09% Taker fees).
- **Automated Rate Limiting** — Proactive request throttling respecting Revolut X public limits (1 req/s) with exponential backoff on HTTP 429 (`Retry-After`).
- **Structured Exceptions** — Hierarchical error handling (`AuthenticationException`, `RateLimitException`, `ApiException`, `OrderValidationException`).
- **AI Agent Skill** — Bundled skill for AI coding assistants (Google Antigravity, Claude, Cursor) to automate exchange workflows. See [AI Agent Integration](guide/ai_agents.md).

---

## Downloads & AI Resources

- **AI Agent Skill (Raw)**: [Download SKILL.md](https://raw.githubusercontent.com/janprikryl1/revolut-x-php/main/.agents/skills/revolut-x-php/SKILL.md)
- **AI Types Reference (Raw)**: [Download types.md](https://raw.githubusercontent.com/janprikryl1/revolut-x-php/main/.agents/skills/revolut-x-php/references/types.md)
- **Skill Folder (GitHub)**: [`.agents/skills/revolut-x-php/`](https://github.com/janprikryl1/revolut-x-php/tree/main/.agents/skills/revolut-x-php/)
- **Repository ZIP**: [Download latest source (main.zip)](https://github.com/janprikryl1/revolut-x-php/archive/refs/heads/main.zip)

## Quick Example

```php
use RevolutX\Client;
use RevolutX\Types\OrderSide;
use RevolutX\Types\Interval;

// 1. Public Market Data (No API keys required)
$client = new Client();
$ticker = $client->getTicker('BTC-EUR');
echo "BTC Price: " . $ticker['last_price'] . " EUR\n";

// 2. Authenticated Trading with 0.00% Maker Fee
$client = new Client(
    apiKey: 'your-api-key',
    privateKeyPath: 'keys/private.pem'
);

// Automatically computes optimal limit price from best bid and submits post_only=true
$order = $client->placeMakerOrder(
    symbol: 'BTC-EUR',
    side: OrderSide::BUY,
    quoteSize: '50.00',
    offset: '0.10'
);
echo "Order ID: " . $order['venue_order_id'] . "\n";
```
