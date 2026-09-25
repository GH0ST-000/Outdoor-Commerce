# Bank of Georgia Payment Manager (Day 21)

Documentation reviewed **2026-09-23** against the official Payment Manager docs. **This integration is not live-verified.** Merchant credentials and Bank of Georgia onboarding are required before production enablement. Automated tests use HTTP fakes and a generated RSA key pair. They never call the live bank API.

**iPay is deprecated and is not used.** Do not call `https://ipay.ge/opay/api/v1`.

Official sources:

- https://api.bog.ge/docs/en/payments/introduction
- https://api.bog.ge/docs/en/payments/authentication
- https://api.bog.ge/docs/en/payments/standard-process/create-order
- https://api.bog.ge/docs/en/payments/standard-process/get-payment-details
- https://api.bog.ge/docs/en/payments/standard-process/callback

See [ADR 0016](adr/0016-bog-payment-manager.md).

## Architecture

`BankOfGeorgiaPaymentProvider` implements Day 20 `PaymentProvider`. Provider-specific HTTP, OAuth, money conversion, status mapping, and RSA verification stay under `App\Domains\Payments\Providers\BankOfGeorgia`. Orders, inventory commitment, and React capability flags are unchanged.

Bank of Georgia does not publish a separate sandbox base URL. Test vs production is merchant credentials against the same official hosts:

- OAuth: `https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token`
- API: `https://api.bog.ge/payments/v1`
- Hosted page: `https://payment.bog.ge`

## Environment

Placeholders are in `backend/.env.example`. Never commit real secrets or put them in `NEXT_PUBLIC_*`.

| Variable | Role |
| --- | --- |
| `BOG_PAYMENT_ENABLED` | Public method + adapter. Default `false`. |
| `BOG_PAYMENT_ENVIRONMENT` | Cache key segment (`test` / `production`). Same URLs. |
| `BOG_PAYMENT_CLIENT_ID` / `BOG_PAYMENT_CLIENT_SECRET` | OAuth HTTP Basic credentials |
| `BOG_PAYMENT_OAUTH_URL` / `BOG_PAYMENT_API_BASE_URL` | Official HTTPS hosts only |
| `BOG_PAYMENT_CALLBACK_PUBLIC_KEY` / `_PATH` / `_PREVIOUS` | RSA public keys (not secrets) |
| `BOG_PAYMENT_CALLBACK_URL` | HTTPS in production, no secrets in the path |
| `BOG_PAYMENT_SUCCESS_URL` / `BOG_PAYMENT_FAILURE_URL` | Both should be `/payment/return` |
| `BOG_PAYMENT_ALLOWED_METHODS` | Day 21: `card` only |
| `BOG_PAYMENT_THEME` | `light` or `dark` |
| `BOG_PAYMENT_ACCOUNT_TAG` | Optional POS tag; omitted when empty |
| Timeouts / token skew / TTL | Short HTTP timeouts; TTL clamped 2–1440 minutes with a safety margin vs order reservation |

Disabled provider: app boots, method hidden, `payments:check-provider bog` reports `enabled: no`. Enabled with incomplete config: method hidden, create/webhook resolve fails with `PAYMENT_PROVIDER_CONFIGURATION`. Production requires HTTPS callback and return URLs. The test provider cannot replace BOG in production.

## OAuth and token cache

`grant_type=client_credentials` as `application/x-www-form-urlencoded` with HTTP Basic `client_id:client_secret`. Response: `access_token`, `token_type`, `expires_in`.

`expires_in` is treated as **seconds of lifetime** when the value is a small integer. The official example JSON shows a large number that looks like a timestamp; the adapter treats values `> 1e12` as unix milliseconds and `> 1e9` as unix seconds.

Tokens are cached in Redis under `payments:oauth:bog:{environment}` with TTL = expiry minus `BOG_PAYMENT_TOKEN_REFRESH_SKEW_SECONDS`. A distributed lock prevents refresh stampedes. Redis outage falls back to in-request authentication. HTTP 401/403 clears the cache and retries authentication once. Tokens are never logged or returned to the browser.

## Create-order mapping

`POST /payments/v1/ecommerce/orders`

- `capture=automatic`, `application_type=web`
- `callback_url` from backend config
- `external_order_id` = payment-attempt `public_id` (unique per attempt)
- `Idempotency-Key` = the same UUID; retries of the same attempt reuse it
- `Accept-Language` `ka` or `en`
- `payment_method: ["card"]`
- `ttl` = remaining reservation minutes minus a safety margin, clamped 2–1440. Insufficient window fails before the HTTP call and does not extend the order reservation
- Basket from immutable order items (`product_id` = item public id). Delivery uses `purchase_units.delivery.amount` when > 0
- Preflight: `sum(line_total) + delivery = order grand_total`. Mismatch does not silently reprice
- Buyer PII is omitted
- Image URLs only if public HTTPS (never localhost or filesystem paths)

A successful create-order is **not** payment success. Normalized status is `requires_action`. Redirect comes from `_links.redirect.href` and must be `https://payment.bog.ge/...`.

## Money conversion

Platform money is integer minor units. Bank of Georgia expects major-unit decimals. Conversion uses integer arithmetic (`intdiv` / modulo), never binary floats. JSON request amounts are injected as two-decimal literals (`100.50`). Payment Details / callbacks often return amount **strings** (`"100.5"`); those are parsed exactly and values with more than two fraction digits are rejected.

## Payment Details and reconciliation

`GET /payments/v1/receipt/{order_id}` with the cached bearer token. Validates provider order id, `external_order_id`, request amount, transfer amount, and currency. Sensitive fields (`payer_identifier`, card expiry, auth codes, buyer email/phone) are ignored: not stored, not logged, not returned publicly.

```bash
php artisan payments:reconcile --provider=bog
php artisan payments:reconcile --provider=bog --attempt={publicId}
```

Same Day 20 outcome service as callbacks. Terminal success is not downgraded.

## Callback and RSA verification

`POST /api/v1/payments/webhooks/bog` — public for the bank, CSRF-exempt only on this path, rate-limited, raw body preserved.

1. Allowlist provider code `bog`
2. Size limit
3. Read `Callback-Signature`
4. `openssl_verify` SHA256withRSA against the **exact raw bytes** (no JSON encode/decode first)
5. Then parse JSON
6. Persist/dedupe inbox
7. HTTP 200 `{ "received": true }` after durable record
8. Apply normalized outcome

Missing/invalid signature → HTTP 400, no payment/order change. Duplicate valid callbacks acknowledge 200 and do not double-commit inventory.

Dedup identity when the bank has no event id: hash of provider, order id, event, status, transaction id, timestamp, payload hash.

Event type is expected to be `order_payment`. Other events never become success.

## Status mapping (`order_status.key` only; labels ignored)

| Provider key | Normalized |
| --- | --- |
| `created` | `requires_action` |
| `processing` | `processing` |
| `completed` | `succeeded` |
| `rejected` | `failed` |
| `refund_requested`, `refunded`, `refunded_partially`, `auth_requested`, `blocked`, `partial_completed` | `manual_review` |
| anything else | `unknown` (never success) |

Completed + transfer amount ≠ request amount → `manual_review`. Merchant reference / provider payment id / amount / currency mismatches → manual review. Late `completed` after expire/cancel/released stock → Day 20 late-payment path (`PAYMENT_SUCCEEDED_AFTER_ORDER_EXPIRATION`). **No automatic refunds.**

Failed/`rejected` allows retry while the order window is open. Reservations stay until the existing order deadline.

Bank of Georgia has no documented standard-order cancel API. Customer cancel marks the attempt cancelled locally (`cancelled_locally`) without claiming a bank refund.

## Frontend

Method label: Bank of Georgia / საქართველოს ბანკი. Hosted-page explanation in both locales. No card fields, no unofficial logo file (name only until a licensed asset is stored). Hidden when disabled or unconfigured. Redirects only to the backend-validated URL. Return page still ignores `success=` query parameters.

## Local mocked testing

1. Keep `BOG_PAYMENT_ENABLED=false` for normal local use, or enable it only in tests.
2. Feature tests call `BogPaymentFixtures::enable()` + `Http::fake`.
3. RSA tests generate an ephemeral key pair — they do not use Bank of Georgia’s private key (merchants never have that key).
4. `php artisan payments:check-provider bog` with the default `.env` should report disabled/not ready without printing secrets.

## Live merchant checklist (blocked until credentials exist)

1. Merchant activation with Bank of Georgia
2. Test and production `client_id` / `client_secret`
3. Confirm current official base URLs
4. Registered callback, success, and failure URLs
5. Activated method: card
6. Official callback public key (rotate via env/path/previous)
7. Optional account/POS tag
8. `payments:check-provider bog` then `--connect` (OAuth only, no payment)
9. Minimum-value test payment on the hosted page
10. Verify signed callback, Payment Details, amount/currency, order confirmed, inventory once, duplicate callback, rejection, reconciliation, timeout recovery
11. Confirm logs contain no secrets
12. Business acceptance before production `BOG_PAYMENT_ENABLED=true`

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Method missing | `BOG_PAYMENT_ENABLED`, credentials, `payments:check-provider bog` |
| Signature failures | Raw body unchanged; `Callback-Signature` present; current public key |
| `unknown` attempt | Create-order timeout; run reconcile |
| Manual review | Amount/currency/reference mismatch or late success |
| Redirect rejected | Host must be `payment.bog.ge`, HTTPS in production |

## Known limitations

No refunds, partial capture, Apple Pay, Google Pay, BNPL, installments, saved cards, or TBC. Live bank verification has not been executed in this repository.
