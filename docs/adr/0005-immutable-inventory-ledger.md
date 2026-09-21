# ADR 0005: Immutable inventory ledger with transactional balance projection

- Status: Accepted (Day 10)
- Date: 2026-09-20

## Context

Commerce requires auditable stock history, warehouse-scoped availability, reservations, and protection against overselling under concurrent checkout and admin adjustments.

## Decision

1. Store stock only on `(warehouse_id, product_variant_id)` balance rows — never on product/variant catalog tables.
2. Record every physical movement as an append-only ledger entry linked to an immutable operation.
3. Maintain `inventory_balances` as a projection updated in the same transaction as ledger writes.
4. Use MySQL transactions with `SELECT … FOR UPDATE`, deterministic lock ordering, and unique idempotency keys.
5. Keep reservations separate from on-hand until commit (sale movement).
6. Use Redis only for optional cache versioning — not as stock authority.

## Consequences

- Corrections are compensating ledger entries, not edits.
- Admin and future checkout flows must call domain actions, not mutate balances directly.
- Production requires scheduler cron for reservation expiration.
