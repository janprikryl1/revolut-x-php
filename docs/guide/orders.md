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
// Retrieve order details by venue_order_id
$detail = $client->getOrder('7a52e92e-8639-4fe1-abaa-68d3a2d5234b');
echo "Status: " . $detail['status'] . "\n";
echo "Filled Size: " . $detail['filled_size'] . "\n";

// Inspect individual partial executions (fills) and maker/taker status
$fills = $client->getOrderFills('7a52e92e-8639-4fe1-abaa-68d3a2d5234b');
foreach ($fills as $fill) {
    $feeType = $fill['im'] ? 'Maker (0.00%)' : 'Taker (0.09%)';
    echo "Price: {$fill['p']} EUR | Qty: {$fill['q']} | Fee: {$feeType}\n";
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
