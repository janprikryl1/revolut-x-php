# User Guide: Smart Maker Strategy (0.00% Fee)

Revolut X offers zero-fee trading (**0.00% Maker**) for orders that provide liquidity to the order book. The PHP SDK provides built-in tools to safely take advantage of this pricing model.

---

## 1. Automated Maker Order Placement

The `placeMakerOrder` method handles the entire workflow in one step:
1. Queries current top of the book (`best_bid` for buy, `best_ask` for sell).
2. Calculates an optimal limit price with safety offset (`offset`).
3. Quantizes the price to the market's minimum price increment (`tickSize`).
4. Submits the order with `post_only = true`.

```php
use RevolutX\Client;
use RevolutX\Types\OrderSide;

$client = new Client(apiKey: '...', privateKeyPath: 'keys/private.pem');

// Buy 50.00 EUR worth of BTC as a zero-fee Maker with a 0.10 EUR safety offset
$order = $client->placeMakerOrder(
    symbol: 'BTC-EUR',
    side: OrderSide::BUY,
    quoteSize: '50.00',
    offset: '0.10',
    tickSize: '0.01'
);

echo "Maker order submitted: " . $order['venue_order_id'] . "\n";
```

---

## 2. Standalone Price Calculation (`MakerOrderStrategy`)

If you want to compute the price ahead of time within your own algorithmic logic:

```php
use RevolutX\Helpers\MakerOrderStrategy;
use RevolutX\Types\OrderSide;

$price = MakerOrderStrategy::calculateMakerPrice(
    side: OrderSide::BUY,
    bestBid: '85200.00',
    offset: '0.20',
    tickSize: '0.01'
);

// Result: "85199.80"
```

---

## 3. Fee Projections & Savings (`FeeCalculator`)

Before placing orders, you can inspect exact fee calculations and compare Maker vs Taker costs:

```php
use RevolutX\Helpers\FeeCalculator;
use RevolutX\Types\OrderSide;

$estimate = FeeCalculator::calculate(
    side: OrderSide::BUY,
    price: '85000.00',
    isMaker: true,
    quoteSize: '1000.00'
);

echo $estimate->explanation;
// Outputs:
// Buy 0.01176471 BTC for 1000.00 EUR at price 85000.00.
// - Execution type: MAKER
// - Fee rate: 0.00 %
// - Fee amount: 0.0000 EUR
// - Net received approx: 0.01176471 BTC
```
