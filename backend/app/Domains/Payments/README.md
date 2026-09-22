# Payments module

## Responsibility

Payment attempts, provider adapters, webhook inbox, reconciliation. Day 20 ships a provider-agnostic core plus a development-only test hosted-redirect provider.

## Data owned

Payment attempts, attempt status history, webhook inbox, payment idempotency records.

## Public contracts

- HTTP: `PublicPaymentMethodController`, `PublicPaymentAttemptController`, `PaymentWebhookController`
- Actions: `CreatePaymentAttemptAction`, `GetPaymentAttemptAction`, `CancelPaymentAttemptAction`, `ListEligiblePaymentMethodsAction`, `ReceivePaymentWebhookAction`, `ReconcilePaymentsAction`, `RetryPaymentWebhookAction`, `SimulateTestPaymentAction`
- Contract: `PaymentProvider`
- Events: `PaymentAttemptCreated`, `PaymentRequiresAction`, `PaymentProcessing`, `PaymentSucceeded`, `PaymentFailed`, `PaymentCancelled`, `PaymentExpired`, `PaymentNeedsManualReview`, `PaymentWebhookReceived`, `PaymentWebhookProcessed`, `PaymentReconciled`, `InventoryCommittedForOrder`

Classes under `Contracts/`, and any Action/Query/DTO explicitly listed here as public, are the only approved entry points for other modules.

## Events this module may publish

Listed above. No customer notifications in Day 20.

## May depend on

Shared; Orders (Actions, Models, Enums, Events, DTOs); Inventory (`CheckoutInventoryService`, reservation models); Operations audit actions (none required today).

## Explicitly outside this module

Order line composition, refunds, real bank SDKs, card PAN/CVV, shipments.

## Structure

See `docs/payments.md` and `docs/adr/0015-provider-agnostic-payment-core.md`.
