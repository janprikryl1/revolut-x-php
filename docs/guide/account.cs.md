# PHP Průvodce: Účet a zůstatky

Autentizované endpointy pro správu peněženek a audit transakcí.

---

## 1. Kontrola zůstatků

```php
use RevolutX\Client;

$client = new Client(apiKey: '...', privateKeyPath: 'keys/private.pem');

// Zůstatky všech měn na účtu
$balances = $client->getBalances();

foreach ($balances as $b) {
    if ((float)$b['total'] > 0) {
        echo "Měna: {$b['currency']} | Dostupné: {$b['available']} | Rezervováno: {$b['reserved']}\n";
    }
}

// Rychlé zjištění jedné měny
$eur = $client->getBalance('EUR');
echo "Disponibilní EUR: {$eur['available']}\n";
```

---

## 2. Transakční historie (Ledger)

Endpoint vrací vklady, výběry, směny měn a vypořádání obchodů s podporou kurzorového stránkování.

Každá transakce je modelována jako **převod mezi dvěma stranami**: `source` (co odešlo) a `destination` (co přišlo). Pole `amount` na nejvyšší úrovni neexistuje — částka je vždy uvnitř `source`/`destination`, protože obě strany mohou být v různých měnách (např. `buy` odesílá EUR a přijímá BTC).

```php
$result = $client->getTransactions(limit: 20);
$transactions = $result['transactions'];
$nextCursor = $result['next_cursor'];

foreach ($transactions as $tx) {
    echo "ID: {$tx['id']} | Typ: {$tx['type']} | Stav: {$tx['status']}\n";
    echo "  Odesláno: {$tx['source']['amount']} {$tx['source']['currency']}\n";
    echo "  Přijato:  {$tx['destination']['amount']} {$tx['destination']['currency']}\n";
}
```

### Struktura transakce

| Pole | Typ | Popis |
| :--- | :--- | :--- |
| `id` | `string` | Unikátní identifikátor transakce (UUID). |
| `status` | `string` | Stav zpracování, např. `completed`. |
| `type` | `string` | Typ transakce: `buy`, `sell`, `receive`, `send`. |
| `source.amount` | `string` | Částka odepsaná ze zdrojové strany. |
| `source.currency` | `string` | Měna zdrojové strany (např. `EUR`, `BTC`). |
| `source.account.type` | `string` | Zdrojový účet: `revolut_x` (burza) nebo `revolut` (hlavní aplikace Revolut). |
| `destination.amount` | `string` | Částka připsaná na cílovou stranu. |
| `destination.currency` | `string` | Měna cílové strany. |
| `destination.account.type` | `string` | Cílový účet: `revolut_x` nebo `revolut`. |
| `created_date` | `int` | Čas vytvoření — Unix timestamp v **milisekundách**. |
| `processed_date` | `int` | Čas vypořádání — Unix timestamp v **milisekundách**. |

Ukázka surové transakce typu `sell` (0.00001 BTC → 0.74 EUR):

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

!!! tip "Jak číst strany podle typu"
    U `buy` je zdrojem fiat a cílem krypto, u `sell` je to naopak. `receive` se `source.account.type = revolut` znamená dobití z hlavního Revolut účtu na Revolut X.

Časové údaje jsou v milisekundách, před formátováním je proto vyděl:

```php
$processedAt = (new DateTimeImmutable())->setTimestamp(intdiv($tx['processed_date'], 1000));
echo $processedAt->format('Y-m-d H:i:s');
```

---

## 3. Historie vlastních obchodů (Private Trades)

Private trades jsou tvoje vlastní exekuce (fills), každá navázaná na objednávku, která ji vytvořila.

!!! warning "Zkrácené názvy polí"
    Tento endpoint vrací **zkrácené klíče** (`p`, `q`, `s`, `tid`, …) — nikoli plné názvy použité jinde v API. Klíče `side`, `price` ani `quantity` neexistují; použij `s`, `p` a `q`.

```php
$history = $client->getAccountTrades('BTC-EUR', limit: 50);

foreach ($history['trades'] as $trade) {
    echo "Strana: {$trade['s']} | Cena: {$trade['p']} {$trade['pc']} | Objem: {$trade['q']} {$trade['qc']}\n";

    // Hodnota exekuce
    $notional = (float)$trade['p'] * (float)$trade['q'];
    echo "  Hodnota: " . number_format($notional, 2) . " {$trade['pc']}\n";
}
```

### Struktura private trade

| Pole | Typ | Popis |
| :--- | :--- | :--- |
| `tid` | `string` | Identifikátor obchodu (fillu). |
| `oid` | `string` | ID objednávky, která exekuci vytvořila — odpovídá `venue_order_id` z `placeOrder()`. |
| `s` | `string` | Strana: `buy` nebo `sell`. |
| `p` | `float` | Realizovaná cena, vyjádřená v `pc`. |
| `pc` | `string` | Měna ceny — kótovaná měna (např. `EUR`). |
| `q` | `string` | Realizované množství, vyjádřené v `qc`. |
| `qc` | `string` | Měna množství — základní měna (např. `BTC`). |
| `aid` | `string` | ID základního aktiva (např. `BTC`). |
| `anm` | `string` | Čitelný název aktiva (např. `Bitcoin`). |
| `tdt` | `int` | Čas obchodu — Unix timestamp v **milisekundách**. |
| `pdt` | `int` | Čas zpracování — Unix timestamp v **milisekundách**. |
| `pn` | `string` | Notace ceny, např. `MONE` (peněžní). |
| `qn` | `string` | Notace množství, např. `UNIT`. |
| `ve` | `string` | Místo exekuce (venue), např. `REVX`. |
| `vp` | `string` | Poskytovatel venue, např. `REVX`. |
| `im` | `string` | Maker příznak: `1` pokud byla exekuce Maker, prázdné pokud Taker. |

Ukázka surové exekuce typu `buy`:

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

!!! note "Poplatek v odpovědi není"
    Odpověď **neobsahuje** výši poplatku. Očekávaný poplatek spočítej pomocí [`FeeCalculator`](maker_strategy.md) z `p`, `q` a role Maker/Taker (`im`), případně dopočítej ze zůstatků.

Čitelné časy a označení role Maker/Taker:

```php
foreach ($history['trades'] as $trade) {
    $when = (new DateTimeImmutable())->setTimestamp(intdiv($trade['tdt'], 1000));
    $role = !empty($trade['im']) ? 'Maker' : 'Taker';

    echo "{$when->format('Y-m-d H:i:s')} | {$role} | {$trade['s']} {$trade['q']} {$trade['qc']}"
       . " @ {$trade['p']} {$trade['pc']}\n";
}
```
