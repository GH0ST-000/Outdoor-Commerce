# Payments (Day 20)

Laravel is the authority for payment amounts, currencies, and completion. The browser never submits an amount. A hosted redirect is not proof of payment. Only a **verified provider webhook or reconciliation status fetch** may complete a payment.

See [ADR 0015](adr/0015-provider-agnostic-payment-core.md) and [ADR 0016](adr/0016-bog-payment-manager.md).

Day 21 adds Bank of Georgia Payment Manager behind `PaymentProvider` — not by changing order, inventory, or frontend business rules. Deprecated iPay is not used.

## Boundaries

| Owns | Does not own |
| --- | --- |
| Payment attempts, webhook inbox, provider adapters, method registry | Order line composition, refunds, card data, real bank credentials |

Cross-module access: Payments uses Orders **Actions / Models / Enums / Events**; Inventory `CheckoutInventoryService`; never Orders/Inventory Services.

## Provider contract

`App\Domains\Payments\Contracts\PaymentProvider`:

- `code()`
- `createPayment(CreateProviderPaymentRequestData)`
- `fetchPaymentStatus(ProviderPaymentReferenceData)`
- `parseAndVerifyWebhook(ProviderWebhookRequestData)`
- `cancelPayment(ProviderPaymentReferenceData)`

SDK objects stay inside the adapter. Registry maps **stable codes** (`test` today) from `config/payments.php`. User input never resolves a class name.

## Payment methods

`GET /api/v1/payment-methods?order_id={orderPublicId}` returns enabled methods eligible for that order (currency, min/max, ownership). The test method is hidden when `APP_ENV=production` or `PAYMENT_TEST_PROVIDER_ENABLED` is false. Credentials are never in the payload.

## Payment attempts

Table `payment_attempts` (UUID `public_id`). Amount/currency copied from the order. History is append-only. One **active** attempt per order is enforced in a transaction (`created`, `pending`, `requires_action`, `processing`, `unknown`). Failed/cancelled/expired attempts remain queryable and allow a new attempt. Succeeded is terminal.

Creation (short DB txn → HTTP outside locks → second txn):

1. Lock order, expire if due, validate payable + reservations + method.
2. Insert attempt `created`, move order to `payment_processing` / payment `pending`.
3. Call provider.
4. Store provider ids, validated redirect, normalized status.
5. On provider timeout: keep the attempt, mark `unknown`, reconcile later.

## State machine

Normalized statuses live in `PaymentStateMachine`. Adapters only translate provider statuses. `succeeded` cannot be downgraded. Duplicate success is a no-op.

Order relationship:

- create attempt: `pending_payment` → `payment_processing`, `unpaid`/`failed` → `pending`
- verified success (eligible): payment `paid`, order `confirmed`, reservations **committed** via Day 10 ledger
- terminal failure: order returns to `pending_payment`/`unpaid` if the window is open (reservation kept)
- late success after expire/cancel/released stock: attempt evidence kept, order `manual_review`, **no** silent stock deduct, **no** auto-refund

## Webhooks

`POST /api/v1/payments/webhooks/{providerCode}`

- CSRF-exempt **only** for this path
- Provider allowlist, payload-size limit, rate limit
- Raw body preserved for signature verification (never pretty-printed first)
- Adapter verifies (test provider: HMAC SHA-256 of `{timestamp}.{raw}` with `X-Test-Signature` / `X-Test-Timestamp`, constant-time compare, timestamp tolerance)
- Inbox `payment_webhooks` dedupes on `(provider, provider_event_id)` and payload hash
- Invalid signature never updates payment/order state
- Processing is idempotent

## Browser return

`/payment/return` is informational. Query flags such as `success=true` must not mark paid. The page polls `GET /api/v1/orders/{id}` until Laravel reports verified `paid` / `confirmed`, or a bounded timeout.

Return and callback URLs are generated server-side. Redirect URLs are allowlisted (scheme, host, length, control characters). No `javascript:` / `data:`.

## Reconciliation

```bash
php artisan payments:reconcile
php artisan payments:reconcile --attempt={publicId}
php artisan payments:retry-webhook {webhookPublicId}
```

Scheduled every minute. Not a replacement for webhooks. Bounded chunks, skips very recent attempts.

## Test provider

Code `test`, method `test_hosted_redirect`. Disabled in production (boot guard). Labeled test-only in the UI. No card forms. Local simulate: `POST /api/v1/payments/test/attempts/{id}/simulate` (ownership + CSRF; not public replay).

## API

| Method | Path |
| --- | --- |
| GET | `/api/v1/payment-methods?order_id=` |
| POST | `/api/v1/orders/{orderPublicId}/payment-attempts` (`Idempotency-Key` required) |
| GET | `/api/v1/orders/{orderPublicId}/payment-attempts/current` |
| GET | `/api/v1/payment-attempts/{id}` |
| POST | `/api/v1/payment-attempts/{id}/cancel` |
| POST | `/api/v1/payments/webhooks/{providerCode}` |

Responses: `private, no-store`. No internal ids, secrets, raw provider payloads, or webhook URLs.

Order GET includes `can_pay`, `can_retry_payment`, `current_payment_attempt`.

## Environment

See `backend/.env.example`: `PAYMENT_TEST_PROVIDER_ENABLED`, `PAYMENT_TEST_WEBHOOK_SECRET` (server-only), `BOG_PAYMENT_*` merchant placeholders, timeouts, rate limits, redirect host allowlist. Never put secrets in `NEXT_PUBLIC_*`.

## Bank of Georgia (Day 21)

Production adapter `bog` / method `bog_hosted_card`. Disabled until `BOG_PAYMENT_ENABLED=true` and merchant credentials exist. Deprecated iPay is not used.

See [payments-bog.md](payments-bog.md) and [ADR 0016](adr/0016-bog-payment-manager.md).

Commands:

```bash
php artisan payments:check-provider bog
php artisan payments:check-provider bog --connect
php artisan payments:reconcile --provider=bog
php artisan payments:reconcile --provider=bog --attempt={publicId}
```
