# User Guide: Account & Balances

Authenticated endpoints for inspecting wallet balances, viewing transaction history, and auditing executed trades.

---

## 1. Checking Balances

```php
use RevolutX\Client;

$client = new Client(apiKey: '...', privateKeyPath: 'keys/private.pem');

// Query balances across all account currencies
$balances = $client->getBalances();

foreach ($balances as $b) {
    if ((float)$b['total'] > 0) {
        echo "Currency: {$b['currency']} | Available: {$b['available']} | Reserved: {$b['reserved']}\n";
    }
}

// Quick lookup for a single currency
$eur = $client->getBalance('EUR');
echo "Available EUR: {$eur['available']}\n";
```

---

## 2. Transaction Ledger History

The transaction ledger endpoint returns deposits, withdrawals, currency exchanges, and trade settlements with cursor-based pagination.

Every transaction is modelled as a **transfer between two legs**: `source` (what left) and `destination` (what arrived). There is no top-level `amount` field — the amount always lives inside `source`/`destination`, because the two legs may use different currencies (e.g. a `buy` spends EUR and receives BTC).

```php
$result = $client->getTransactions(limit: 20);
$transactions = $result['transactions'];
$nextCursor = $result['next_cursor'];

foreach ($transactions as $tx) {
    echo "ID: {$tx['id']} | Type: {$tx['type']} | Status: {$tx['status']}\n";
    echo "  Out: {$tx['source']['amount']} {$tx['source']['currency']}\n";
    echo "  In:  {$tx['destination']['amount']} {$tx['destination']['currency']}\n";
}
```

### Transaction Structure

| Field | Type | Description |
| :--- | :--- | :--- |
| `id` | `string` | Unique transaction identifier (UUID). |
| `status` | `string` | Processing state, e.g. `completed`. |
| `type` | `string` | Transaction type: `buy`, `sell`, `receive`, `send`. |
| `source.amount` | `string` | Amount debited from the source leg. |
| `source.currency` | `string` | Currency of the source leg (e.g. `EUR`, `BTC`). |
| `source.account.type` | `string` | Origin account: `revolut_x` (exchange) or `revolut` (main Revolut app). |
| `destination.amount` | `string` | Amount credited to the destination leg. |
| `destination.currency` | `string` | Currency of the destination leg. |
| `destination.account.type` | `string` | Target account: `revolut_x` or `revolut`. |
| `created_date` | `int` | Creation time — Unix timestamp in **milliseconds**. |
| `processed_date` | `int` | Settlement time — Unix timestamp in **milliseconds**. |

Example of a raw `sell` transaction (0.00001 BTC → 0.74 EUR):

```php
[
    'id' => '6aca5f93-3529-a8dd-a88b-7fd105c824d5',
    'status' => 'completed',
    'type' => 'sell',
    'source' => [
        'amount' => '0.00001000',
        'currency' => 'BTC',
        'account' => ['type' => 'revolut_x'],
    ],
    'destination' => [
        'amount' => '0.74',
        'currency' => 'EUR',
        'account' => ['type' => 'revolut_x'],
    ],
    'created_date' => 1791647635654,
    'processed_date' => 1791647642966,
]
```

!!! tip "Reading the legs by type"
    For `buy` the source is fiat and the destination is crypto; for `sell` it is reversed. A `receive` with `source.account.type = revolut` is a top-up from your main Revolut account into Revolut X.

Timestamps are in milliseconds, so divide before formatting:

```php
$processedAt = (new DateTimeImmutable())->setTimestamp(intdiv($tx['processed_date'], 1000));
echo $processedAt->format('Y-m-d H:i:s');
```

---

## 3. Private Trade History

Private trades are your own executed fills, each linked to the order that produced it.

!!! warning "Abbreviated field names"
    This endpoint returns **short, abbreviated keys** (`p`, `q`, `s`, `tid`, …) — not the verbose names used elsewhere in the API. There are no `side`, `price`, or `quantity` keys; use `s`, `p`, and `q`.

```php
$history = $client->getAccountTrades('BTC-EUR', limit: 50);

foreach ($history['trades'] as $trade) {
    echo "Side: {$trade['s']} | Price: {$trade['p']} {$trade['pc']} | Qty: {$trade['q']} {$trade['qc']}\n";

    // Notional value of the fill
    $notional = (float)$trade['p'] * (float)$trade['q'];
    echo "  Value: " . number_format($notional, 2) . " {$trade['pc']}\n";
}
```

### Private Trade Structure

| Field | Type | Description |
| :--- | :--- | :--- |
| `tid` | `string` | Trade (fill) identifier. |
| `oid` | `string` | ID of the order that produced this fill — matches `venue_order_id` from `placeOrder()`. |
| `s` | `string` | Side: `buy` or `sell`. |
| `p` | `float` | Execution price, expressed in `pc`. |
| `pc` | `string` | Price currency — the quote currency (e.g. `EUR`). |
| `q` | `string` | Executed quantity, expressed in `qc`. |
| `qc` | `string` | Quantity currency — the base currency (e.g. `BTC`). |
| `aid` | `string` | Base asset ID (e.g. `BTC`). |
| `anm` | `string` | Human-readable asset name (e.g. `Bitcoin`). |
| `tdt` | `int` | Trade time — Unix timestamp in **milliseconds**. |
| `pdt` | `int` | Processed time — Unix timestamp in **milliseconds**. |
| `pn` | `string` | Price notation, e.g. `MONE` (monetary). |
| `qn` | `string` | Quantity notation, e.g. `UNIT`. |
| `ve` | `string` | Execution venue, e.g. `REVX`. |
| `vp` | `string` | Venue provider, e.g. `REVX`. |
| `im` | `string` | Maker flag: `1` when the fill was a Maker execution, empty when it was a Taker. |

Example of a raw `buy` fill:

```php
[
    'tdt' => 1791647668224,
    'aid' => 'BTC',
    'anm' => 'Bitcoin',
    'p' => 74059.5,
    'pc' => 'EUR',
    'pn' => 'MONE',
    'q' => '0.00006751',
    'qc' => 'BTC',
    'qn' => 'UNIT',
    've' => 'REVX',
    'pdt' => 1791647668224,
    'vp' => 'REVX',
    'tid' => '01fe035493603ee083af048511364379',
    's' => 'buy',
    'oid' => 'de238453-7ac1-41dd-9ab9-e631f01ca4c6',
    'im' => '1',
]
```

!!! note "No fee field"
    The fill payload does **not** include a fee amount. Use [`FeeCalculator`](maker_strategy.md) to derive the expected fee from `p`, `q`, and your Maker/Taker role (`im`), or reconcile against your balances.

Readable timestamps and a Maker/Taker label:

```php
foreach ($history['trades'] as $trade) {
    $when = (new DateTimeImmutable())->setTimestamp(intdiv($trade['tdt'], 1000));
    $role = !empty($trade['im']) ? 'Maker' : 'Taker';

    echo "{$when->format('Y-m-d H:i:s')} | {$role} | {$trade['s']} {$trade['q']} {$trade['qc']}"
       . " @ {$trade['p']} {$trade['pc']}\n";
}
```
