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

The transaction ledger endpoint returns deposits, withdrawals, fee charges, and trade settlements with cursor-based pagination:

```php
$result = $client->getTransactions(limit: 20);
$transactions = $result['transactions'];
$nextCursor = $result['next_cursor'];

foreach ($transactions as $tx) {
    echo "ID: {$tx['id']} | Type: {$tx['type']} | Amount: {$tx['amount']}\n";
}
```

---

## 3. Private Trade History

Unlike the public trade feed, private trades contain your executed trades with exact fee amounts and execution roles:

```php
$history = $client->getAccountTrades('BTC-EUR', limit: 50);

foreach ($history['trades'] as $trade) {
    echo "Side: {$trade['side']} | Price: {$trade['price']} EUR | Qty: {$trade['quantity']}\n";
}
```
