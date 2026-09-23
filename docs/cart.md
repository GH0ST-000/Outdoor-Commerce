# Cart (Day 17)

Laravel is the authority for cart ownership, eligibility, current price, promotions, availability, totals, merging, and expiry. MySQL is the persistent source of truth. The Next.js storefront treats every cart response as canonical and never calculates totals.

See [ADR 0012](adr/0012-persistent-server-authoritative-cart.md).

## Lifecycle

Statuses: `active`, `merged`, `converted`, `expired`, `abandoned`.

- `GET /api/v1/cart` returns an empty canonical cart when none exists. It does **not** insert a row for anonymous window-shoppers.
- The first mutation creates a guest or authenticated cart.
- Successful mutations increment `version`, refresh `last_activity_at`, and extend `expires_at`.
- Merged, converted, expired, and abandoned carts are immutable. A converted shopper who adds again gets a **new** active cart.
- `php artisan carts:expire` marks due active carts as expired in chunks and clears `guest_token_hash`. It does not expire converted or merged carts.

Retention (config, not magic numbers):

| Cart | Config | Default |
| --- | --- | --- |
| Guest | `CART_GUEST_TTL_DAYS` | 30 days after last activity |
| Authenticated | `CART_AUTHENTICATED_TTL_DAYS` | 90 days |

## Guest identity

1. First mutation generates a high-entropy opaque token (`CartTokenHasher`).
2. Laravel sets it as an HttpOnly cookie (`outdoor_guest_cart` by default).
3. MySQL stores only HMAC-SHA256(`token`, `APP_KEY`).
4. The public cart UUID is not a credential. Knowing `id` does not grant access.
5. The cookie is excluded from Laravel cookie encryption so the same opaque value works on stateful (Sanctum) and stateless API requests. CSRF still applies to cookie-authenticated mutations.

Cookie flags follow `CART_COOKIE_SECURE` / `CART_COOKIE_SAME_SITE` (production: Secure + SameSite aligned with the session). Path `/`. Domain unset unless `CART_COOKIE_DOMAIN` is set.

Do not put the token in URLs, HTML, analytics, `NEXT_PUBLIC_*`, or logs (`CartLogger` strips `token` / `cookie` keys).

## Authenticated ownership

Sanctum identity is the owner. Client-supplied user or cart IDs are ignored. One active cart per user is enforced in `CartResolver` with `lockForUpdate` (MySQL cannot express a partial unique index on `(user_id) WHERE status = active`).

Login and register attempt merge after authentication. The storefront also `POST /api/v1/cart/merge` after auth. Merge never accepts source/destination IDs from the client.

## Items

Every purchasable line requires a real `variant_id`. Re-adding the same variant increases the existing line. Unique `(cart_id, variant_id)`. Quantity is a positive integer capped by `CART_MAX_LINE_QUANTITY` (default 12) and current sellable availability.

`unit_price_at_add_minor` is a snapshot for change detection only. Reads and mutations re-quote through `PublicCatalogPricing`. After the shopper receives a cart that includes `PRICE_CHANGED`, the snapshot is updated.

Optional display snapshots (`product_name_at_add`, `sku_at_add`) are fallbacks when catalog rows disappear. Current names, media, and prices come from catalog hydration (`CartCatalogHydrator` + `PublicMediaPresenter` thumbnail/card derivatives — never original files or local paths).

## Pricing and promotions

`CartPricingService` calls the Day 11 public catalog pricing contract. Promotions apply only when currently valid. Expired promotions disappear on the next recalculation (`PROMOTION_ENDED`). Shipping, payment fees, coupons, and checkout taxes are **out of scope**. Copy: “Shipping and final availability are calculated during checkout.”

Integer minor units only.

## Inventory

**Cart operations do not reserve stock.** `CartAvailabilityService` reads `PublicInventoryAvailability`. Adding or increasing above sellable quantity returns `CART_INSUFFICIENT_STOCK`. Decreasing and removing remain possible when stock has fallen. Unavailable lines stay visible with issues until the shopper removes them. Exact warehouse quantities are not exposed.

Day 18 checkout revalidates price and availability and creates short-lived reservations. See [checkout.md](checkout.md).

## Concurrency and idempotency

Mutations run in a transaction with `lockForUpdate` on the cart (and line when needed). Optional `cart_version` must match or the API returns HTTP 409 `CART_VERSION_CONFLICT` with the latest cart when available. The storefront replaces local state and does not retry in a loop.

`Idempotency-Key` is required on mutations. Keys are scoped to `user:{id}` or `guest:{token_hash}` (guest token is generated before the first add so the first request and its retry share a scope). Identical payload replays the stored canonical body. A different payload with the same key returns 409 `CART_IDEMPOTENCY_CONFLICT`. Records expire after `CART_IDEMPOTENCY_TTL_HOURS` (default 24). Stored in MySQL (`cart_idempotency_records`). Redis is not required for correctness.

## Merge

1. Lock carts by ascending id.
2. Adopt the guest cart if the user has none.
3. Otherwise move lines: matching variants sum quantities then cap by line max and sellable stock.
4. Unique guest lines are preserved until `CART_MAX_UNIQUE_LINES`.
5. Source becomes `merged`, `merged_into_cart_id` is set, guest hash is cleared, cookie is forgotten.
6. Quantity caps produce `QUANTITY_ADJUSTED_ON_MERGE`.
7. Repeat merge is a no-op (source already merged / cookie gone).

## API

All responses: `{ data: Cart }` with `Cache-Control: private, no-store`. Rate limits: `cart.read` / `cart.mutate`.

| Method | Path | Auth |
| --- | --- | --- |
| GET | `/api/v1/cart` | Cookie or Sanctum |
| POST | `/api/v1/cart/items` | Cookie or Sanctum |
| PATCH | `/api/v1/cart/items/{itemPublicId}` | Owner |
| DELETE | `/api/v1/cart/items/{itemPublicId}` | Owner; repeat delete is safe |
| DELETE | `/api/v1/cart` | Owner |
| POST | `/api/v1/cart/merge` | Sanctum + cookie |

Add body: `{ "variant_id": 1, "quantity": 1, "cart_version": 3 }`. Optional `product_id` is verified against the variant. Client prices are prohibited.

### Error codes

`CART_PRODUCT_UNAVAILABLE`, `CART_VARIANT_UNAVAILABLE`, `CART_INSUFFICIENT_STOCK`, `CART_QUANTITY_LIMIT_EXCEEDED`, `CART_CURRENCY_MISMATCH`, `CART_VERSION_CONFLICT`, `CART_IDEMPOTENCY_CONFLICT`, `CART_NOT_MUTABLE`, `CART_ITEM_NOT_FOUND`, plus standard validation / CSRF / rate-limit envelopes.

Line issue codes (on the cart payload): `PRODUCT_UNAVAILABLE`, `VARIANT_UNAVAILABLE`, `INSUFFICIENT_STOCK`, `QUANTITY_LIMIT`, `QUANTITY_ADJUSTED_ON_MERGE`, `PRICE_CHANGED`, `PRICE_INCREASED`, `PRICE_DECREASED`, `PROMOTION_ENDED`, `PROMOTION_APPLIED`, `CART_EXPIRED`.

## Frontend

`CartProvider` keeps an in-memory cache only. No localStorage cart. No JS-readable guest credential. Mutations send `Idempotency-Key` and `cart_version`. `BroadcastChannel` (`outdoor-cart`) triggers revalidation across tabs without sending cart data. Flag: `NEXT_PUBLIC_CART_ENABLED=true`.

Routes: mini-cart drawer in the header; `/cart` full page; `/checkout` for Day 18 quoting.

Direct Add on product cards only when `variant_count === 1`, a `default_variant_id` exists, the card is purchasable, and the price is not a range.

## Query budget (typical, excluding migration)

| Operation | Pattern |
| --- | --- |
| Empty GET | Resolve actor + 1 cart lookup (miss) |
| Cart with N unique lines | 1 cart + 1 items + batched products/variants/media + 1 pricing quote batch + 1 availability batch |
| Add / update | Transaction: lock cart, lock/find line, validate, write, presenter batches above |
| Guest merge | Lock 1–2 carts, lock lines, move/update, presenter batches |

Avoid Meilisearch for cart hydration.

## Local testing

```bash
cd backend && php artisan migrate && php artisan test --compact tests/Feature/Cart
php artisan carts:expire
cd ../frontend && npm run test:run
```

Open the storefront, add a variant, refresh (guest cookie persists), sign in (merge), open `/cart`.

## Day 18 boundary

Checkout quoting must re-quote prices, re-check availability, and **then** reserve inventory. Do not treat cart totals as payable. Do not reuse merged/expired carts as orders.

## Security

Ownership on every mutation. IDOR tests cover user A vs user B. Guest token is not sequential. CSRF remains enabled. Cart endpoints are private, no-store. Controllers stay thin; rules live in `App\Domains\Cart`.
