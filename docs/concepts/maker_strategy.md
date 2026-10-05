# Concept: Maker vs Taker Fee Structure

One of the greatest competitive advantages of **Revolut X** is its fee schedule:

| Execution Type | Fee Rate | Description |
| :--- | :--- | :--- |
| **Maker** | **0.00 %** | Order adds liquidity to the order book (Limit order resting in the book). |
| **Taker** | **0.09 %** | Order takes liquidity from the order book (Market order or immediate limit match). |

For systematic or automated trading strategies, the difference between 0.00% and 0.09% represents substantial compound fee savings over time.

---

## The `post_only` Protection

When submitting standard limit orders, volatile market movements may cause the order to match immediately with an existing order. In that scenario, the exchange would classify it as a **Taker** and charge 0.09%.

The `post_only = true` flag:
- Guarantees the order enters the order book strictly as a **Maker**.
- If the order would match immediately upon placement, the exchange automatically cancels/rejects it without charging any fees.

---

## Dynamic Maker Price Calculation

The SDK's `MakerOrderStrategy` evaluates live order book depth and calculates an optimal limit price with a configurable safety offset:

- **For BUY orders**:
  $$\text{price} = \text{best\_bid} - \text{offset}$$
- **For SELL orders**:
  $$\text{price} = \text{best\_ask} + \text{offset}$$

The calculated price is then quantized to the market's required minimum price increment (`tick_size`).

---

## Code Example

```php
use RevolutX\Client;
use RevolutX\Types\OrderSide;

$client = new Client(apiKey: '...', privateKeyPath: 'keys/private.pem');

// Automatically fetches the live book, applies 0.10 EUR offset, and submits post_only=true
$order = $client->placeMakerOrder(
    symbol: 'BTC-EUR',
    side: OrderSide::BUY,
    quoteSize: '50.00',
    offset: '0.10'
);
```
