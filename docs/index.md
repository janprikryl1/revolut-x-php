# Revolut X PHP SDK
<em>PHP klientská knihovna pro REST API kryptoměnové burzy Revolut X (v1.0).</em>

---

## Přehled

Tato knihovna poskytuje kompletní integraci s **Revolut X Crypto Exchange REST API (v1.0)** v moderním PHP 8.0+. Knihovna je navržena pro maximální výkon s nulovými externími runtime závislostmi, nativním Ed25519 šifrováním (`ext-sodium`) a automatickou optimalizací pro nulové poplatky (**0.00% Maker poplatek**).

Knihovna vznikla v rámci diplomové práce na **VŠB – Technické univerzitě Ostrava** (FEI).

- **Autor**: Bc. Jan Přikryl
- **Balíček**: `janprikryl/revolutx`
- **Repozitář**: [github.com/janprikryl1/revolut-x-php](https://github.com/janprikryl1/revolut-x-php)

---

## Hlavní funkce

- **Podpora Revolut X API v1.0** — Veřejná data (tickery, kniha objednávek, svíčky), správa objednávek i účetnictví.
- **Nativní Ed25519 šifrování** — Bleskové podepisování pomocí vestavěného rozšíření `ext-sodium`.
- **Nulové runtime závislosti** — Využívá pouze standardní PHP rozšíření (`ext-curl`, `ext-json`, `ext-sodium`).
- **Kompatibilita s PHP 8.0+** — Přísné typování `declare(strict_types=1);` a třídní enumy.
- **Smart Maker Strategie** — Automatický výpočet cenových offsetů pro garantovaný **0.00% poplatek** (oproti 0.09% u Taker objednávek).
- **Automatický rate limiting** — Respektování limitu 1 req/s a exponenciální backoff při HTTP 429 (`Retry-After`).
- **Strukturované výjimky** — Přehledná hierarchie chyb (`AuthenticationException`, `RateLimitException`, `ApiException`, `OrderValidationException`).

---

## Rychlá ukázka

```php
use RevolutX\Client;
use RevolutX\Types\OrderSide;
use RevolutX\Types\Interval;

// 1. Veřejná tržní data (bez API klíče)
$client = new Client();
$ticker = $client->getTicker('BTC-EUR');
echo "Cena BTC: {$ticker['last_price']} EUR\n";

// 2. Autentizované obchodování s 0% Maker poplatkem
$client = new Client(
    apiKey: 'your-api-key',
    privateKeyPath: 'keys/private.pem'
);

$order = $client->placeMakerOrder(
    symbol: 'BTC-EUR',
    side: OrderSide::BUY,
    quoteSize: '50.00'
);
echo "Order ID: " . $order['venue_order_id'] . "\n";
```
