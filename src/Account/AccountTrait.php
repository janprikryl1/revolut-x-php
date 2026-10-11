<?php

declare(strict_types=1);

namespace RevolutX\Account;

trait AccountTrait
{
    /**
     * Retrieve current balances for all currencies on the account.
     *
     * @return list<array<string, mixed>>
     */
    public function getBalances(): array
    {
        $resp = $this->http->request('GET', '/balances', null, null, true);
        return is_array($resp) ? $resp : [];
    }

    /**
     * Retrieve the balance for a specific currency.
     *
     * @param string $currency Currency symbol (e.g. 'EUR', 'BTC', 'ETH').
     * @return array<string, mixed>
     */
    public function getBalance(string $currency): array
    {
        $balances = $this->getBalances();
        $target = strtoupper($currency);

        foreach ($balances as $item) {
            if (is_array($item) && isset($item['currency']) && strtoupper((string)$item['currency']) === $target) {
                return $item;
            }
        }

        return [
            'currency' => $target,
            'available' => '0.00',
            'reserved' => '0.00',
            'total' => '0.00',
        ];
    }

    /**
     * Retrieve account transaction history (ledger).
     *
     * Each transaction is a two-leg transfer and has no top-level 'amount' key:
     * read the amount and currency from 'source' (debited) and 'destination'
     * (credited), which may be denominated in different currencies.
     *
     * Transaction shape:
     *   id: string, status: string, type: 'buy'|'sell'|'receive'|'send',
     *   source: array{amount: string, currency: string, account: array{type: string}},
     *   destination: array{amount: string, currency: string, account: array{type: string}},
     *   created_date: int (ms), processed_date: int (ms)
     *
     * @return array{transactions: list<array<string, mixed>>, next_cursor: string|null}
     */
    public function getTransactions(int $limit = 50, ?string $cursor = null): array
    {
        $params = ['limit' => $limit];
        if ($cursor !== null) {
            $params['cursor'] = $cursor;
        }

        $resp = $this->http->request('GET', '/transactions', $params, null, true);
        $transactions = [];
        $nextCursor = null;

        if (is_array($resp)) {
            $transactions = isset($resp['data']) && is_array($resp['data']) ? $resp['data'] : [];
            $nextCursor = $resp['metadata']['next_cursor'] ?? null;
        }

        return [
            'transactions' => $transactions,
            'next_cursor' => $nextCursor,
        ];
    }

    /**
     * Retrieve private (your own) trade history for a trading pair.
     *
     * Fills use abbreviated keys - there are no 'side', 'price' or 'quantity'
     * keys, and no fee amount is returned.
     *
     * Trade shape:
     *   tid: string (fill id), oid: string (order id), s: 'buy'|'sell',
     *   p: float (price in 'pc'), pc: string (quote currency), pn: string,
     *   q: string (quantity in 'qc'), qc: string (base currency), qn: string,
     *   aid: string, anm: string, ve: string, vp: string,
     *   tdt: int (ms), pdt: int (ms), im: string ('1' = Maker, '' = Taker)
     *
     * @param string $symbol Trading pair (e.g. 'BTC-EUR').
     * @return array{trades: list<array<string, mixed>>, next_cursor: string|null}
     */
    public function getAccountTrades(
        string $symbol,
        int $limit = 50,
        ?string $cursor = null
    ): array {
        $apiSymbol = strtoupper(str_replace('/', '-', $symbol));
        $params = ['limit' => $limit];
        if ($cursor !== null) {
            $params['cursor'] = $cursor;
        }

        $resp = $this->http->request('GET', "/trades/private/{$apiSymbol}", $params, null, true);
        $trades = [];
        $nextCursor = null;

        if (is_array($resp)) {
            $trades = isset($resp['data']) && is_array($resp['data']) ? $resp['data'] : [];
            $nextCursor = $resp['metadata']['next_cursor'] ?? null;
        }

        return [
            'trades' => $trades,
            'next_cursor' => $nextCursor,
        ];
    }
}
