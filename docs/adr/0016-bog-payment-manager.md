# ADR 0016 — Bank of Georgia Payment Manager as the first production payment adapter

## Status

Accepted (Day 21). Documentation reviewed **2026-09-23**.

## Context

Day 20 defined a provider-agnostic payment core. Day 21 must collect real card payments in Georgia without placing bank-specific rules in orders, inventory, controllers, or React.

Bank of Georgia previously published an iPay API under `https://ipay.ge/opay/api/v1`. That API is deprecated and must not be used.

The currently supported product is **Payment Manager**:

- https://api.bog.ge/docs/en/payments/introduction
- https://api.bog.ge/docs/en/payments/authentication
- https://api.bog.ge/docs/en/payments/standard-process/create-order
- https://api.bog.ge/docs/en/payments/standard-process/get-payment-details
- https://api.bog.ge/docs/en/payments/standard-process/callback

## Decision

1. **Do not use iPay.** The adapter talks only to `oauth2.bog.ge` and `api.bog.ge/payments/v1`.

2. **Card entry stays on the bank-hosted page.** This application never collects PAN, CVV, or expiry. The storefront shows a hosted-redirect method only.

3. **OAuth client credentials stay server-side.** `client_id` / `client_secret` are Laravel env values. Access tokens live in Redis (with an in-request fallback) and are never sent to Next.js.

4. **Callback signatures are verified against the exact raw body** using SHA256withRSA and the `Callback-Signature` header, before `json_decode`. The public key is not a secret; the currently reviewed official key is pinned in config with review date 2026-09-23 and can be rotated via env/path/previous key.

5. **Browser redirects are non-authoritative.** Success and failure return URLs both land on `/payment/return`, which polls Laravel. Query flags and the bank’s choice of success vs fail URL cannot mark an order paid.

6. **Payment Details supports reconciliation** when callbacks are delayed or lost. `payments:reconcile --provider=bog` uses the same normalized outcome service as callbacks.

7. **Platform order totals remain authoritative.** Basket lines and `total_amount` are built from the immutable order snapshot. Minor units convert to two-decimal major units without floating-point arithmetic. Amount/currency mismatches go to manual review.

8. **Inventory commits only after verified payment**, through Day 20’s success service and the Day 10 ledger. Duplicate callbacks do not deduct twice.

9. **Unsupported Payment Manager features stay disabled.** Day 21 allowlists `card` only. Google Pay, Apple Pay, P2P, loyalty, BNPL, loans, gift cards, split payments, saved cards, and refunds are out of scope.

10. **The provider stays disabled without credentials.** Missing `client_id` / `client_secret` hides the method. The application still boots. There is no fallback to the development test provider in production.

## Consequences

Adding another Georgian bank later means a new adapter plus registry mapping — not a rewrite of orders or React capability flags. Live verification still requires merchant onboarding and bank-issued credentials; mocked HTTP tests do not prove a production merchant setup.
