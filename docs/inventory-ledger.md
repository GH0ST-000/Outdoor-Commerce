# Inventory ledger (Day 10)

Variant-level stock is tracked per warehouse using an immutable ledger and a transactional balance projection. Product and variant catalog tables never store quantities.

## Quantities

- **On hand** — physical units at a warehouse
- **Reserved** — units held by active reservations
- **Unreserved** — `on_hand - reserved`
- **Safety stock** — buffer excluded from customer availability
- **Available to sell** — `max(0, on_hand - reserved - safety_stock)`

## Architecture

- **MySQL** is authoritative (row locks, transactions, unique idempotency keys).
- **Redis** may cache availability versions only; never authorize reservations from cache alone.
- **Operations** — one logical command per receipt/adjustment/transfer/commit with a unique `Idempotency-Key`.
- **Ledger entries** — immutable signed deltas with resulting `on_hand_after` / `reserved_after`.
- **Reservations** — lifecycle records; active reservations increase `reserved` without reducing `on_hand` until commit.

## Admin API

See `routes/api.php` under `/api/v1/admin/warehouses` and `/api/v1/admin/inventory`. Stock-changing POST endpoints require the `Idempotency-Key` header.

## Scheduler

```bash
php artisan schedule:run   # production cron every minute
php artisan inventory:expire-reservations
```

## Verification

```bash
php artisan inventory:verify-balances
php artisan inventory:verify-balances --repair   # explicit repair mode
```

## Checkout integration

Use `App\Domains\Inventory\Contracts\CheckoutInventoryService` from Cart/Checkout/Orders modules.

## Tests

```bash
php artisan test --filter=Inventory
```

MySQL concurrency tests are skipped unless CI runs against MySQL.
