# User Guide: Order Management

The orders module provides full capabilities for placing Market and Limit orders, checking execution progress, querying historical trades, and canceling open orders.

---

## 1. Market Orders

A Market Order executes immediately at the best available price in the order book (subject to the 0.09% Taker fee). Provide either `quoteSize` (amount in quote currency, e.g. EUR) or `baseSize` (amount in base currency, e.g. BTC):

```php
use RevolutX\Client;
use RevolutX\Types\OrderSide;

$client = new Client(apiKey: '...', privateKeyPath: 'keys/private.pem');

// Buy 50.00 EUR worth of BTC at market price
$order = $client->placeMarketOrder(
    symbol: 'BTC-EUR',
    side: OrderSide::BUY,
    quoteSize: '50.00'
);

echo "Market order submitted. ID: " . $order['venue_order_id'] . "\n";
```

---

## 2. Limit Orders

A Limit Order waits in the order book until the specified price is matched. To guarantee zero fees, set `postOnly: true`:

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

## 3. Order Status & Execution Fills

```php
// Retrieve order details by order ID
$detail = $client->getOrder('7a52e92e-8639-4fe1-abaa-68d3a2d5234b');
echo "Status: " . $detail['status'] . "\n";
echo "Filled: " . $detail['filled_quantity'] . " / " . $detail['quantity'] . "\n";
echo "Remaining: " . $detail['leaves_quantity'] . "\n";
echo "Fee paid: " . $detail['total_fee'] . " " . $detail['fee_currency'] . "\n";

// Inspect individual partial executions (fills) and maker/taker status
$fills = $client->getOrderFills('7a52e92e-8639-4fe1-abaa-68d3a2d5234b');
foreach ($fills as $fill) {
    $feeType = !empty($fill['im']) ? 'Maker (0.00%)' : 'Taker (0.09%)';
    echo "Price: {$fill['p']} {$fill['pc']} | Qty: {$fill['q']} | Fee: {$feeType}\n";
}
```

!!! warning "Quantities are not called `size`"
    The order payload has no `filled_size` or `size` keys. Sizes are named `quantity` (ordered), `filled_quantity` (executed), and `leaves_quantity` (still open).

### Order Detail Structure

| Field | Type | Description |
| :--- | :--- | :--- |
| `id` | `string` | Order identifier (the ID you passed in). |
| `client_order_id` | `string` | Your own idempotency ID, auto-generated when not supplied. |
| `symbol` | `string` | Trading pair in **slash** form (`BTC/EUR`) — note that requests take the dash form (`BTC-EUR`). |
| `side` | `string` | `buy` or `sell`. |
| `type` | `string` | `limit` or `market`. |
| `quantity` | `string` | Ordered quantity in the base currency. |
| `filled_quantity` | `string` | Quantity already executed. |
| `leaves_quantity` | `string` | Quantity still open in the book. |
| `filled_amount` | `string` | Value already executed, in the quote currency. |
| `price` | `string` | Limit price (for limit orders). |
| `total_fee` | `string` | Fee charged so far — `0` for an unfilled or Maker order. |
| `fee_currency` | `string` | Currency of `total_fee` (e.g. `EUR`). |
| `status` | `string` | Lifecycle state, e.g. `new` (open, unfilled). |
| `time_in_force` | `string` | e.g. `gtc`. |
| `execution_instructions` | `list<string>` | Active instructions, e.g. `['post_only']` for a Maker order. |
| `created_date` | `int` | Creation time — Unix timestamp in **milliseconds**. |
| `updated_date` | `int` | Last update time — Unix timestamp in **milliseconds**. |

Example of a resting `post_only` limit buy (nothing filled yet):

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

Checking whether an order is fully, partially, or not yet filled:

```php
$detail = $client->getOrder($orderId);

if ((float)$detail['leaves_quantity'] <= 0.0) {
    echo "Fully filled\n";
} elseif ((float)$detail['filled_quantity'] > 0.0) {
    echo "Partially filled: {$detail['filled_quantity']} of {$detail['quantity']}\n";
} else {
    echo "Resting in the book, status: {$detail['status']}\n";
}

// Confirm the Maker (post_only) instruction was accepted
if (in_array('post_only', $detail['execution_instructions'] ?? [], true)) {
    echo "Maker order — 0.00% fee\n";
}
```

---

## 4. Querying Active Orders & Cancellation

```php
// List all open orders for a trading pair
$activeOrders = $client->getActiveOrders('BTC-EUR');

// Cancel a specific order by ID
$client->cancelOrder('7a52e92e-8639-4fe1-abaa-68d3a2d5234b');

// Cancel all open orders for a given symbol
$client->cancelAllOrders('BTC-EUR');
```
