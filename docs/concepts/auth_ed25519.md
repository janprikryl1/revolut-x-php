# Concept: Ed25519 Authentication

The Revolut X REST API requires cryptographic signing using the asymmetric **Ed25519** algorithm (RFC 8032 / Edwards-curve Digital Signature Algorithm) for all authenticated endpoints (order management, account balances, and trade history).

---

## Canonical Signature Message Format

The message to be signed is constructed by concatenating five components in strict order:

$$\text{message} = \text{timestamp} + \text{METHOD} + \text{path} + \text{query\_string} + \text{body}$$

1. **`timestamp`** — Current Unix timestamp in milliseconds. Must be synchronized with exchange server time.
2. **`METHOD`** — Uppercase HTTP verb (`GET`, `POST`, `DELETE`).
3. **`path`** — Relative endpoint path, normalized to begin with `/api` (e.g. `/api/1.0/orders`).
4. **`query_string`** — Alphabetically sorted query parameters joined by `&`, URL-encoded. Empty string if no parameters.
5. **`body`** — Minified JSON request body without whitespace after delimiters (`,`, `:`). Empty string if no body.

---

## Required HTTP Headers

Every authenticated request sent to Revolut X must include:

- `X-Revx-API-Key` — Your public API key from Revolut X settings.
- `X-Revx-Timestamp` — Millisecond timestamp matching the value used in the canonical message.
- `X-Revx-Signature` — Base64-encoded 64-byte Ed25519 detached signature.
- `Content-Type: application/json` — Present only when a request body is sent.

---

## Implementation in the PHP SDK

The SDK handles signing transparently inside `RevolutX\Auth\Signer` using PHP's native `ext-sodium` extension (`sodium_crypto_sign_detached`):

```php
use RevolutX\Client;

$client = new Client(
    apiKey: 'your-api-key',
    privateKeyPath: 'keys/private.pem'
);

// All subsequent private calls are automatically signed:
$balances = $client->getBalances();
```

---

## Generating an Ed25519 Key Pair

To generate a new PKCS#8 PEM Ed25519 key pair with OpenSSL:

```bash
openssl genpkey -algorithm ed25519 -out keys/private.pem
openssl pkey -in keys/private.pem -pubout -out keys/public.pem
```

Upload `keys/public.pem` in your **Revolut X** exchange account settings to receive your `API Key`.
