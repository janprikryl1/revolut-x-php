# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

This file starts at `1.0.1`. Releases before it are recorded only as git tags —
see the commit history for what they contained.

---

## [1.1.0] - 2026-10-12
### Fixed
- **`getTrades()` failed on every call.** It built `/public/trades/{symbol}`,
  which is not a public path — the venue answered each request with
  `{"message":"Unauthenticated access","code":2000}`, so the method returned
  that error payload instead of trades. Public trades are served from
  `/public/trades/all` with the symbol as a **query parameter**. Its `$before` /
  `$after` cursors were likewise invented: the endpoint paginates with a single
  `cursor` and bounds the window with `start_date` / `end_date`. `$limit` was
  passed through unclamped, and the venue rejects anything above 100 with HTTP
  400.

  The signature changes accordingly — `getTrades(string $symbol, int $limit =
  100, $startDate = null, $endDate = null, ?string $cursor = null)` — as does
  the return value, now `['trades' => [...], 'next_cursor' => ?string]` to match
  `getAccountTrades()`. Both are breaking changes on paper only: no call could
  have succeeded before, so there is no working code to break. `$limit` is
  clamped into 1–100, and the time bounds accept milliseconds, a
  `DateTimeInterface`, or a date string like the rest of the library.
- **`getTicker()` returned a random region's price.** `/public/tickers` serves
  one row per pair *and* region — 777 rows for 455 pairs, because 322 symbols
  are quoted in both `EEA` and `UK`, in every case at a different price and
  volume. `getTicker()` returned the first match, and the exchange reorders the
  list between calls: across three consecutive requests the leading region
  flipped for 251 of those 322 symbols. Callers therefore saw prices jump
  (`RAD/USD` alternating between `0.3166` at volume 63,403 and `0.3193` at
  volume 1,015) with nothing having happened on the market.

  This reached further than reporting: `placeMakerOrder()` without an explicit
  `$price` derives its limit price from `calculateMakerPrice()` → `getTicker()`,
  so a real post-only order was priced off a randomly chosen order book. With
  the default `$offset` of `0.10` and inter-region gaps far larger than that,
  the resulting order could be rejected for crossing or rest far from the touch.

  `getTicker()` now returns the most liquid quote — highest `volume_24h`, region
  name breaking an exact tie — so repeated calls agree. Single-region pairs,
  including the documented `BTC/EUR`, are unaffected. To target one region,
  filter `getTickers()` on `['region']`.

### Added
- **`tests/MarketTest.php`**: first test coverage for `MarketTrait`, which was
  previously untested. Covers the region selection above, symbol-format
  matching, numeric (not lexicographic) comparison of the string volumes, and
  the endpoint, parameters and pagination of `getTrades()`.

### Documentation
- **`getTickers()`** no longer describes itself as returning tickers "for all
  pairs", which implied one row per pair; it documents the per-region
  duplication, the unstable ordering, and the `region` field.
- This changelog.

## [1.0.1] - 2026-10-11

Earlier releases: `1.0.0` (2026-10-11), `0.1.0` (2026-10-10), `0.0.7`, `0.0.6`
and `0.0.1` (all 2026-10-05).
