# PHP Průvodce: Správa objednávek

Modul objednávek umožňuje zadávat tržní i limitní příkazy, kontrolovat stav rozpracovaných objednávek a rušit aktivní pokyny.

---

## 1. Tržní objednávka (Market Order)

Tržní objednávka se páruje okamžitě za nejlepší dostupnou cenu v knize (Taker poplatek 0.09%). Zadávejte buď `quoteSize` (hodnota v EUR) nebo `baseSize` (množství v BTC):

```php
use RevolutX\Client;
use RevolutX\Types\OrderSide;

$client = new Client(apiKey: '...', privateKeyPath: 'keys/private.pem');

// Nákup BTC za 50.00 EUR
$order = $client->placeMarketOrder(
    symbol: 'BTC-EUR',
    side: OrderSide::BUY,
    quoteSize: '50.00'
);

echo "Příkaz odeslán. ID: " . $order['venue_order_id'] . "\n";
```

---

## 2. Limitní objednávka (Limit Order)

Limitní objednávka čeká v knize na dosažení zadané ceny. Pro garanci nulového poplatku použijte `postOnly: true`:

```php
use RevolutX\Types\OrderSide;
use RevolutX\Types\TimeInForce;

$order = $client->placeLimitOrder(
    symbol: 'BTC-EUR',
    side: OrderSide::BUY,
    price: '75000.00',
    quoteSize: '100.00',
    postOnly: true,
    timeInForce: TimeInForce::GTC
);
```

---

## 3. Zjištění stavu a exekuce objednávky

```php
// Detail objednávky podle jejího ID
$detail = $client->getOrder('7a52e92e-8639-4fe1-abaa-68d3a2d5234b');
echo "Stav: " . $detail['status'] . "\n";
echo "Vyplněno: " . $detail['filled_quantity'] . " z " . $detail['quantity'] . "\n";
echo "Zbývá: " . $detail['leaves_quantity'] . "\n";
echo "Poplatek: " . $detail['total_fee'] . " " . $detail['fee_currency'] . "\n";

// Získání jednotlivých exekucí (fills) a ověření maker statusu
$fills = $client->getOrderFills('7a52e92e-8639-4fe1-abaa-68d3a2d5234b');
foreach ($fills as $fill) {
    $feeType = !empty($fill['im']) ? 'Maker (0.00%)' : 'Taker (0.09%)';
    echo "Cena: {$fill['p']} {$fill['pc']}, Objem: {$fill['q']}, Poplatek: {$feeType}\n";
}
```

!!! warning "Objemy se nejmenují `size`"
    Odpověď neobsahuje klíče `filled_size` ani `size`. Objemy se jmenují `quantity` (zadáno), `filled_quantity` (vyplněno) a `leaves_quantity` (zbývá otevřeno).

### Struktura detailu objednávky

| Pole | Typ | Popis |
| :--- | :--- | :--- |
| `id` | `string` | Identifikátor objednávky (to ID, které jsi předal). |
| `client_order_id` | `string` | Vlastní idempotenční ID; pokud ho nezadáš, vygeneruje se automaticky. |
| `symbol` | `string` | Pár ve formátu s **lomítkem** (`BTC/EUR`) — pozor, požadavky se posílají s pomlčkou (`BTC-EUR`). |
| `side` | `string` | `buy` nebo `sell`. |
| `type` | `string` | `limit` nebo `market`. |
| `quantity` | `string` | Zadané množství v základní měně. |
| `filled_quantity` | `string` | Již realizované množství. |
| `leaves_quantity` | `string` | Množství stále otevřené v knize. |
| `filled_amount` | `string` | Již realizovaná hodnota v kótované měně. |
| `price` | `string` | Limitní cena (u limitních příkazů). |
| `total_fee` | `string` | Dosud účtovaný poplatek — `0` u nevyplněné nebo Maker objednávky. |
| `fee_currency` | `string` | Měna pole `total_fee` (např. `EUR`). |
| `status` | `string` | Stav objednávky, např. `new` (otevřená, nevyplněná). |
| `time_in_force` | `string` | Např. `gtc`. |
| `execution_instructions` | `list<string>` | Aktivní instrukce, např. `['post_only']` u Maker objednávky. |
| `created_date` | `int` | Čas vytvoření — Unix timestamp v **milisekundách**. |
| `updated_date` | `int` | Čas poslední změny — Unix timestamp v **milisekundách**. |

Ukázka ležící `post_only` limitní nákupní objednávky (ještě nevyplněné):

```php
[
    'id' => '2fc4863e-2699-4d4f-9215-780e55826570',
    'client_order_id' => '9006daa3-525c-428c-85b0-982d1315c7f9',
    'symbol' => 'BTC/EUR',
    'side' => 'buy',
    'type' => 'limit',
    'quantity' => '0.00006748',
    'filled_quantity' => '0',
    'leaves_quantity' => '0.00006748',
    'filled_amount' => '0',
    'price' => '74091.97',
    'total_fee' => '0',
    'fee_currency' => 'EUR',
    'status' => 'new',
    'time_in_force' => 'gtc',
    'execution_instructions' => ['post_only'],
    'created_date' => 1791661554000,
    'updated_date' => 1791661554000,
]
```

Zjištění, zda je objednávka vyplněná celá, částečně, nebo vůbec:

```php
$detail = $client->getOrder($orderId);

if ((float)$detail['leaves_quantity'] <= 0.0) {
    echo "Kompletně vyplněno\n";
} elseif ((float)$detail['filled_quantity'] > 0.0) {
    echo "Částečně vyplněno: {$detail['filled_quantity']} z {$detail['quantity']}\n";
} else {
    echo "Leží v knize, stav: {$detail['status']}\n";
}

// Ověření, že instrukce Maker (post_only) byla přijata
if (in_array('post_only', $detail['execution_instructions'] ?? [], true)) {
    echo "Maker objednávka — poplatek 0.00%\n";
}
```

---

## 4. Aktivní objednávky a rušení

```php
// Seznam všech otevřených příkazů
$activeOrders = $client->getActiveOrders('BTC-EUR');

// Zrušení konkrétního příkazu
$client->cancelOrder('7a52e92e-8639-4fe1-abaa-68d3a2d5234b');

// Zrušení všech otevřených příkazů pro daný měnový pár
$client->cancelAllOrders('BTC-EUR');
```
