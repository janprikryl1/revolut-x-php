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

### Práce s časem (přečtěte si před stavbou „dnešního" přehledu)

!!! warning "Svíčky jsou zarovnané na UTC a nejnovější svíčka se ještě tvoří"
    Tyto dvě věci jsou nejčastější příčinou toho, že si dva svíčkové přehledy
    zdánlivě odporují, i když jsou data pod nimi identická:

    1. **`start`, `since` a `until` jsou unixové časy v _milisekundách_ a svíčky jsou
       zarovnané na UTC — ne na vaši lokální zónu.** U `Interval::D1` začíná denní
       svíčka v `00:00` UTC, což je `01:00` v zóně `Europe/Prague` (CET) — svíčka za
       „lokální kalendářní den" tedy vůbec neexistuje. Když vezmete „dnešní půlnoc"
       v UTC a když ji vezmete v zóně `UTC+2`, vyberete dvě různé sady svíček —
       obě správné, jen odpovídají na jinou otázku.
    2. **Poslední svíčka ještě není uzavřená.** Její `close`, `high`, `low` a `volume`
       se mění až do konce intervalu, takže dva běhy pár minut po sobě vrátí pro
       stejný `start` legitimně jiná čísla.

Když předáte **oba** parametry `since` i `until`, API vrátí jen uzavřené svíčky —
ta rozestavěná se vynechá, což je přesně to, co chcete pro stabilní přehled:

```php
use RevolutX\Types\Interval;

$interval = Interval::H1;

// Zónu zvolte explicitně, nespoléhejte na date_default_timezone_get().
$tz = new DateTimeZone('Europe/Prague');

$startOfDay = (new DateTimeImmutable('today midnight', $tz))->getTimestamp() * 1000;
$endOfDay   = (new DateTimeImmutable('tomorrow midnight', $tz))->getTimestamp() * 1000;

$candles = $client->getCandles(
    'BTC-EUR',
    interval: $interval,
    since: $startOfDay,
    until: $endOfDay
);

foreach ($candles as $candle) {
    $time = (new DateTimeImmutable('@' . intdiv((int) $candle['start'], 1000)))
        ->setTimezone($tz)
        ->format('Y-m-d H:i');

    echo "{$time} | O: {$candle['open']} | H: {$candle['high']} | L: {$candle['low']} | C: {$candle['close']}\n";
}
```

Pokud naopak živou, nedokončenou svíčku chcete (třeba pro zobrazení typu ticker),
předejte samotné `since` — pak je zahrnutá jako poslední prvek.

!!! warning "Nefiltrujte celou odpověď na straně PHP u krátkých intervalů"
    Bez `since`/`until` vrací endpoint **až 1000 svíček**. Pro `H1` nebo `D1` to
    jeden den pokryje s rezervou, ale `1000 × 1m ≈ 16,7 hodiny` — takže když
    stáhnete `Interval::M1` bez omezení a „dnešek" si vyfiltrujete v PHP, po
    přibližně `16:40` lokálního času vám tiše zmizí ranní svíčky. Vždy request
    omezte přes `since`/`until`.
