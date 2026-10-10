# PHP API Reference

Comprehensive reference of classes, traits, helper functions, and exceptions provided by the `janprikryl/revolutx` package.

---

## 1. Main Client: `RevolutX\Client`
*(Also aliased as `RevolutX\RevolutXClient`)*

### Constructor
```php
public function __construct(
    ?string $apiKey = null,
    ?string $privateKeyPath = null,
    ?string $privateKeyBytes = null,
    string $baseUrl = 'https://revx.revolut.com/api',
    string $apiVersion = '1.0',
    float $requestDelay = 0.85,
    int $timeout = 15,
    int $maxRetries = 3
)
```

### Public Market Data (`MarketTrait`)
- `getPairs(): array` — Returns configurations and trading limits for all active pairs.
- `getPair(string $symbol): array` — Returns configuration for a specific trading pair.
- `getCurrencies(): array` — Returns configurations for all supported currencies.
- `getTickers(): array` — Returns current tickers for all trading pairs.
- `getTicker(string $symbol): array` — Returns current ticker for a specific pair.
- `getOrderBook(string $symbol, int $depth = 10): array` — Returns order book bids and asks.
- `getCandles(string $symbol, int $interval = Interval::H1, ?int $since = null, ?int $until = null): array` — Returns OHLCV candles.
- `getTrades(string $symbol, int $limit = 100, ?string $before = null, ?string $after = null): array` — Returns public trade history.

### Order Management (`OrdersTrait`)
- `placeOrder(string|array $symbolOrPayload, ?string $side = null, string $orderType = OrderType::MARKET, ...): array` — Generic order placement.
- `placeMarketOrder(string $symbol, string $side, ?string $baseSize = null, ?string $quoteSize = null, ?string $clientOrderId = null): array` — Submits a market order.
- `placeLimitOrder(string $symbol, string $side, string $price, ..., bool $postOnly = false, string $timeInForce = TimeInForce::GTC): array` — Submits a limit order.
- `calculateMakerPrice(string $symbol, string $side, mixed $offset = '0.10', mixed $tickSize = '0.01'): string` — Computes optimal limit price from live book.
- `placeMakerOrder(string $symbol, string $side, ?string $price = null, mixed $offset = '0.10', mixed $tickSize = '0.01', ...): array` — Places a guaranteed Maker order (0.00% fee).
- `getOrder(string $orderId): array` — Retrieves order status and fill progress. Keys are `id`, `quantity`, `filled_quantity`, `leaves_quantity`, `filled_amount`, `total_fee` — there is no `filled_size`. See [Order Management](../guide/orders.md#order-detail-structure).
- `getOrderFills(string $orderId): array` — Retrieves individual execution fills.
- `getActiveOrders(?string $symbol = null): array` — Retrieves currently open orders.
- `getHistoricalOrders(?string $symbol = null, int $limit = 50, ?string $cursor = null): array` — Retrieves historical orders with cursor pagination.
- `cancelOrder(string $orderId): array` — Cancels an open order by ID.
- `cancelAllOrders(?string $symbol = null): array` — Cancels all open orders across one or all pairs.

### Account & Balances (`AccountTrait`)
- `getBalances(): array` — Retrieves balances across all currencies.
- `getBalance(string $currency): array` — Retrieves balance for a specific currency.
- `getTransactions(int $limit = 50, ?string $cursor = null): array` — Retrieves ledger transaction history as `['transactions' => [...], 'next_cursor' => ?string]`. Each transaction is a two-leg transfer (`source`/`destination`, each with `amount`, `currency`, `account.type`) — there is no top-level `amount` key. See [Account & Balances](../guide/account.md#transaction-structure).
- `getAccountTrades(string $symbol, int $limit = 50, ?string $cursor = null): array` — Retrieves private executed fills as `['trades' => [...], 'next_cursor' => ?string]`. Fills use abbreviated keys (`s` side, `p` price, `q` quantity, `im` maker flag) and carry no fee amount. See [Account & Balances](../guide/account.md#private-trade-structure).

---

## 2. Types & Constants (`RevolutX\Types\*`)

- **`OrderSide`**: `OrderSide::BUY` (`'buy'`), `OrderSide::SELL` (`'sell'`).
- **`OrderType`**: `OrderType::MARKET` (`'market'`), `OrderType::LIMIT` (`'limit'`).
- **`TimeInForce`**: `TimeInForce::GTC` (`'gtc'`), `TimeInForce::IOC` (`'ioc'`), `TimeInForce::FOK` (`'fok'`).
- **`Interval`**: `Interval::M1` (1), `Interval::M5` (5), `Interval::M15` (15), `Interval::H1` (60), `Interval::H4` (240), `Interval::D1` (1440).
- **`FeeEstimate`**: DTO containing fee calculations, effective rate, and breakdown summary.

---

## 3. Helpers & Utilities (`RevolutX\Helpers\*`)

- **`MakerOrderStrategy`**: `calculateMakerPrice(string $side, mixed $bestBid = null, mixed $bestAsk = null, mixed $lastPrice = null, mixed $offset = '0.10', mixed $tickSize = '0.01'): string`
- **`FeeCalculator`**: `calculate(string $side, mixed $price, bool $isMaker, mixed $baseSize = null, mixed $quoteSize = null): FeeEstimate`
- **`OrderPayloadBuilder`**: Order payload validation and JSON payload construction.
- **`SymbolNormalizer`**: Symbol format normalization (`BTC/EUR` $\rightarrow$ `BTC-EUR`).

---

## 4. Exceptions (`RevolutX\Exceptions\*`)

```text
RevolutXException (base exception)
├── AuthenticationException    — Missing/invalid credentials, bad PEM key, or HTTP 401/403
├── RateLimitException         — Exceeded rate limit (HTTP 429), contains getRetryAfterSeconds()
├── ApiException               — Non-2xx response with getStatusCode(), getErrorCode()
├── OrderValidationException   — Invalid parameters before sending to exchange
└── NetworkException           — Connection timeout or cURL network failure
```
