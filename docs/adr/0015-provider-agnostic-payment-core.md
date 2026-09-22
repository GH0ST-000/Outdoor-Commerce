# ADR 0015 — Provider-agnostic payment core with verified webhook authority

## Status

Accepted (Day 20).

## Context

Day 19 creates pending-payment orders with authoritative totals and short-lived inventory reservations. Day 20 must collect payment without a real Georgian bank yet, while remaining safe to attach one on Day 21.

Threats to avoid:

- Browser-supplied amounts or `?success=true` marking an order paid
- Card data entering the application
- Duplicate webhooks double-committing inventory
- Late provider success after stock was released confirming a sale that cannot be fulfilled
- Bank-specific logic leaking into orders, controllers, or React

## Decision

1. **Orders own authoritative totals.** Payment attempts copy `grand_total_minor` and `currency` from the stored order. Clients cannot send amount, currency, return URL, or callback URL.

2. **Providers are isolated behind `PaymentProvider` adapters.** A registry maps stable codes from configuration. Controllers stay thin. React is presentational.

3. **Browser redirects are not authoritative.** Hosted-redirect URLs are allowlisted. `/payment/return` polls Laravel. Query parameters never complete payment.

4. **Verified webhook (or provider status fetch) is authoritative.** Signature verification is owned by the adapter. Constant-time compare for HMAC. Unverified payloads never mutate payment or order state.

5. **Payment attempts are immutable history.** Failed attempts are not reused as if new. One active attempt per order is enforced transactionally.

6. **Webhook processing is durable and idempotent.** Inbox rows dedupe on provider event id and payload hash. Duplicate delivery is a no-op for inventory and order transitions.

7. **Inventory is committed only after verified payment**, through Day 10 `CheckoutInventoryService::commit` and the ledger. Quantity comes from the order reservation, not webhook line items. Duplicate success does not deduct twice (reservation commit idempotency keys).

8. **Late payments require manual review.** Success after expiration, cancellation, or released reservations preserves provider evidence, moves the order to `manual_review`, and does not silently restock-sell. Refunds wait for a real provider.

9. **Reconciliation handles lost webhooks.** `payments:reconcile` uses the same normalized outcome service as webhooks.

10. **No card data enters the application.** Hosted redirect only. Test provider is development-only and cannot be enabled in production.

## Consequences

Day 21 adds a Georgian adapter without rewriting order creation, inventory commitment, or frontend capability flags (`can_pay`, `can_retry_payment`). Operators must resolve `manual_review` cases until refunds exist.
