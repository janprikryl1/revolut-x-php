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

!!! note "Jeden ticker na pár *a region*"
    `getTickers()` vrací řádek pro každou kombinaci páru a regionu, nikoli jeden
    na pár — většina symbolů je kotována v `EEA` i `UK` za odlišné ceny a
    objemy, takže se v seznamu objeví dvakrát. Region poznáte z `$t['region']`
    a nespoléhejte se na pořadí seznamu: burza ho mezi voláními přehazuje.

    `getTicker()` tuto nejednoznačnost řeší tím, že vrátí **nejlikvidnější**
    kotaci daného symbolu (nejvyšší `volume_24h`). Pokud potřebujete konkrétní
    region, vyfiltrujte si ho sami:

    ```php
    $uk = array_values(array_filter(
        $client->getTickers(),
        fn(array $t): bool => $t['symbol'] === 'RAD/USD' && $t['region'] === 'UK'
    ))[0];
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

### Práce s časem: dvě věci, které je potřeba vědět

Dva svíčkové přehledy postavené nad stejnými daty si můžou snadno odporovat.
Téměř vždy za tím stojí jeden z následujících dvou důvodů, ne chyba.

**Svíčky jsou krájené podle UTC, ne podle vašeho lokálního dne.** Hodinová svíčka
začíná v celou hodinu UTC a denní svíčka `D1` v `00:00` UTC — což je v zóně
`Europe/Prague` `01:00` lokálního času. Svíčka za váš lokální kalendářní den tedy
neexistuje. Stejně tak „dnes od půlnoci" znamená něco jiného v UTC a něco jiného
v Praze; obě čtení jsou správná, jen odpovídají na jinou otázku. Zónu proto
zvolte vědomě a výstup jí označte.

**Nejnovější svíčka není dokončená.** Její `close`, `high`, `low` a `volume` se
hýbou až do konce intervalu. Když stejný skript pustíte dvakrát s odstupem pár
minut, poslední řádek se legitimně bude lišit, zatímco všechny řádky nad ním
zůstanou totožné.

#### Stabilní přehled za jeden den

Předejte **oba** parametry `since` i `until`. API pak vrátí jen uzavřené svíčky,
takže ta nedokončená vám nerozhodí součty:

```php
use RevolutX\Types\Interval;

// Zónu zvolte vědomě — nepřebírejte date_default_timezone_get().
$tz = new DateTimeZone('Europe/Prague');

$candles = $client->getCandles(
    'BTC-EUR',
    interval: Interval::H1,
    since: new DateTimeImmutable('today midnight', $tz),
    until: new DateTimeImmutable('tomorrow midnight', $tz)
);

foreach ($candles as $candle) {
    // 'start' se vrací v milisekundách — viz „Omezení časového okna" níže.
    $time = (new DateTimeImmutable('@' . intdiv((int) $candle['start'], 1000)))
        ->setTimezone($tz)
        ->format('Y-m-d H:i');

    echo "{$time} | O: {$candle['open']} | H: {$candle['high']} | L: {$candle['low']} | C: {$candle['close']}\n";
}
```

Chcete naopak tu živou, nedokončenou svíčku — třeba pro zobrazení typu ticker?
Předejte samotné `since` a přijde jako poslední prvek.

!!! warning "Nestahujte všechno a nefiltrujte v PHP"
    Bez `since`/`until` vrací endpoint **posledních 1000 svíček**. Pro `H1` nebo
    `D1` to jeden den pokryje s rezervou, ale 1000 minutových svíček je jen
    asi 16,7 hodiny. Když tedy stáhnete `Interval::M1` bez omezení a „dnešek" si
    vyfiltrujete v PHP, někdy po `16:40` lokálního času vám ráno tiše vypadne ze
    začátku rozsahu — bez chyby, jen chybějící řádky. Omezte místo toho request.

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
