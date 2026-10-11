# PHP Průvodce: Tržní data

Revolut X poskytuje veřejné koncové body pro čtení konfigurací trhů, cenových tickerů, hloubky trhu (order book) a historických svíček (OHLCV).

---

## 1. Měnové páry a konfigurace

```php
use RevolutX\Client;

$client = new Client();

// Získání všech aktivních párů
$pairs = $client->getPairs();

// Detail konkrétního páru
$btcEur = $client->getPair('BTC-EUR');
echo "Minimální velikost objednávky: " . $btcEur['min_order_size'] . " BTC\n";
echo "Cenový krok: " . $btcEur['quote_step'] . " EUR\n";
```

---

## 2. Cenové tickery

Tickery vracejí aktuální nejlepší nabídku (`bid`), nejlepší poptávku (`ask`) a cenu posledního obchodu (`last_price`):

```php
// Ticker pro jeden pár
$ticker = $client->getTicker('BTC-EUR');
echo "Bid: {$ticker['bid']} EUR | Ask: {$ticker['ask']} EUR\n";

// Všechny tickery
$allTickers = $client->getTickers();
```

---

## 3. Kniha objednávek (Order Book)

```php
// Stažení hloubky trhu pro BTC-EUR (top 10 úrovní)
$orderBook = $client->getOrderBook('BTC-EUR', depth: 10);

echo "Nejlepší nabídka (Bid 1): " . $orderBook['bids'][0][0] . " EUR (objem: " . $orderBook['bids'][0][1] . " BTC)\n";
echo "Nejlepší poptávka (Ask 1): " . $orderBook['asks'][0][0] . " EUR (objem: " . $orderBook['asks'][0][1] . " BTC)\n";
```

---

## 4. Svíčková data (OHLCV)

Podporované časové rámce jsou definovány ve třídě `Interval`:

| Konstanta | Hodnota (minuty) | Popis |
| :--- | :--- | :--- |
| `Interval::M1` | `1` | 1 minuta |
| `Interval::M5` | `5` | 5 minut |
| `Interval::M15` | `15` | 15 minut |
| `Interval::H1` | `60` | 1 hodina |
| `Interval::H4` | `240` | 4 hodiny |
| `Interval::D1` | `1440` | 1 den |

```php
use RevolutX\Types\Interval;

$candles = $client->getCandles('BTC-EUR', interval: Interval::H1);

foreach (array_slice($candles, -5) as $candle) {
    echo "Čas: {$candle['start']} | Open: {$candle['open']} | Close: {$candle['close']} | Objem: {$candle['volume']}\n";
}
```

### Omezení časového okna

`$since` a `$until` přijímají kteroukoli z těchto hodnot:

| Hodnota | Význam |
| :--- | :--- |
| `1700000000000` | Unix timestamp v **milisekundách**, předá se beze změny. |
| `new DateTimeImmutable('2024-01-01', new DateTimeZone('UTC'))` | Převede se podle vlastní časové zóny objektu. |
| `'2024-01-01T00:00:00Z'` | Datum jako string — naparsuje se, offset se respektuje. |
| `'-7 days'` | Relativní výraz, jak ho chápe `DateTimeImmutable`. |
| `'1700000000000'` | String z 12+ číslic se bere jako milisekundy. |

```php
use RevolutX\Types\Interval;

$candles = $client->getCandles(
    'BTC-EUR',
    interval: Interval::H1,
    since: new DateTimeImmutable('-7 days', new DateTimeZone('UTC')),
    until: new DateTimeImmutable('now', new DateTimeZone('UTC')),
);
```

String bez explicitního offsetu se chápe jako **UTC**, ne podle serverového
`date.timezone` — stejný kód tedy vybere stejné okno na každém stroji. Prázdný
nebo nenaparsovatelný string vyhodí `InvalidArgumentException`.

!!! warning "Čísla jsou milisekundy, ne sekundy"
    Čísla se nikdy nepřepočítávají, takže `since: time()` neskončí chybou — jen
    dostanete okno začínající v lednu 1970, tedy prázdný nebo nepoužitelný
    rozsah. Použijte `time() * 1000`, nebo předejte `DateTimeImmutable` a nechte
    převod na SDK. V odpovědích jsou hodnoty taky v milisekundách:
    `$candle['start']` je potřeba před `date()` vydělit tisícem.

    Stejný převod je k dispozici i samostatně přes
    `RevolutX\Helpers\TimestampConverter::toMilliseconds()`.

### Hodnoty přicházející z requestu

Vstup od uživatele si převeďte sami, místo abyste posílali dál surový string —
parser v SDK je pohodlí, ne validační vrstva:

```php
use RevolutX\Types\Interval;

$userTz = new DateTimeZone('Europe/Prague');
$raw = trim($_GET['from'] ?? '');          // např. '2024-01-01' z <input type="date">

if ($raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) !== 1) {
    http_response_code(400);
    exit(json_encode(['error' => 'from must be YYYY-MM-DD']));
}

// Midnight v zóně *uživatele*, ne v UTC.
$since = $raw === '' ? null : new DateTimeImmutable("{$raw} 00:00:00", $userTz);

$candles = $client->getCandles('BTC-EUR', Interval::H1, since: $since);
```

Čtyři věci, které kousnou, když hodnota přiteče přímo z prohlížeče:

- **String bez offsetu je UTC.** `'2024-01-01'` je UTC midnight, ale uživatel
  v Praze, který vybral 1. ledna, myslí 31. prosince 23:00 UTC. Hranici proto
  postavte v jeho zóně a předejte `DateTimeImmutable` jako výše.
  Z JavaScriptu jsou jednoznačné `d.getTime()` (milisekundy) i `d.toISOString()`
  (nese `Z`); `'2024-01-01T13:30'` z `<input type="datetime-local">`
  jednoznačné **není** — nemá offset, takže se přečte jako UTC.
- **Datum se lomítky je americké.** `DateTimeImmutable` čte `'01/02/2024'` jako
  2. ledna, takže uživatel, který myslel 1. února, dostane jiný den — a bez
  jakékoli chyby. Trvejte na `YYYY-MM-DD`.
- **Nevyplněné pole je prázdný string, ne chybějící hodnota.** HTML formulář
  pošle `?from=` i u nedotčeného inputu a `''` vyhodí
  `InvalidArgumentException` — proto jsou v příkladu kontroly na `=== ''`.
- **Datum bez oddělovačů se odmítne, nehádá se.** `'2024'` a `'20240101'`
  vyhodí výjimku místo toho, aby skončily v roce 1970, protože krátká řada
  číslic je mezi datem a timestampem nerozhodnutelná. Za milisekundy se berou
  až 12+ číslic.

Projdou i relativní výrazy jako `'-7 days'` nebo `'now'`, což je další důvod
ukotvit povolený tvar na hranici aplikace a nepouštět dovnitř libovolné stringy
z requestu.
