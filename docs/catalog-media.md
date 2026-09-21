# Catalog / Media (Day 9)

## Overview

Day 9 adds a **local filesystem media pipeline** for product and variant galleries:

```text
Upload (admin) → original on media_private → ProcessMediaAsset job (queue: media)
                → derivatives on media_public → responsive manifest in API
```

Originals never appear in JSON. Only derivative URLs under `/storage/media/…` are exposed once an asset is `ready`.

Day 12 will expose the same manifest on the public catalog API; the storefront adapter layer is already shaped for `ResponsiveMedia`.

## Storage layout

| Disk | Laravel name | Path | Purpose |
| --- | --- | --- | --- |
| Originals | `media_private` | `storage/app/private/media/originals/` | Master bytes, not web-accessible |
| Derivatives | `media_public` | `storage/app/public/media/derivatives/` | WebP/JPEG/PNG/AVIF presets |

Run once per environment:

```bash
php artisan storage:link
```

Configure `APP_URL` so derivative URLs resolve correctly (Next.js `images.remotePatterns` includes localhost storage for dev).

**Backup both trees** in production: `storage/app/private/media` and `storage/app/public/media`.

## Queue worker

Derivative generation is asynchronous:

```bash
php artisan queue:work --queue=media,default
```

Upload endpoints return **202 Accepted** with attachment rows in `pending` / `processing` until the worker finishes.

## Admin API

Base: `/api/v1/admin` (Sanctum session + CSRF).

| Method | Path | Permission | Notes |
| --- | --- | --- | --- |
| GET | `/products/{id}/media` | `catalog.view` | Gallery manifest |
| POST | `/products/{id}/media` | `catalog.manage` | Multipart `files[]`, 202 |
| POST | `/products/{id}/media/reorder` | `catalog.manage` | `{ attachment_ids: number[] }` |
| PATCH | `/products/{id}/media/{attachment}` | `catalog.manage` | Alt, caption, focal point |
| PATCH | `/products/{id}/media/{attachment}/primary` | `catalog.manage` | Set primary |
| DELETE | `/products/{id}/media/{attachment}` | `catalog.manage` | Remove attachment |
| POST | `/products/{id}/media/{attachment}/retry` | `catalog.manage` | Re-queue failed asset, 202 |
| GET | `/media/{asset}/status` | `catalog.view` | Poll processing |

The same routes exist under `/products/{id}/variants/{variantId}/media`.

### Attachment JSON (admin)

Produced by `MediaPresentationService`:

- Identity: `id`, `asset_id`, `role`, `status`, `is_primary`, `sort_order`
- File: `original_filename`, `mime_type`, `byte_size`, `width`, `height`, `failure_code`
- Content: `alt_text`, `caption`, `translations[]`, `focal_point` `{ x, y }` (0–1)
- Delivery: `sources` — per format (`webp`, `jpeg`, …) `{ srcset, presets: { thumbnail|card|…: { url, width, height, byte_size } } }` or `null` until `ready`

## Upload limits (config/media.php)

- MIME: JPEG, PNG, WebP
- Max file size: 15 MB (default)
- Max per request: 10 files
- Gallery caps: 20 product / 10 variant (configurable)

Blocked extensions (SVG, executables, archives, etc.) are rejected in domain validation.

## Presets

| Preset | Typical use |
| --- | --- |
| `thumbnail` | Admin thumbs, PDP thumb strip |
| `card` | PLP cards |
| `card_large` | Comfortable grid |
| `detail` | PDP main image |
| `zoom` | Lightbox / zoom (future) |

## Maintenance commands

```bash
php artisan media:cleanup-orphans   # unattached assets after grace period
php artisan media:recover-stuck     # re-queue pending/processing past thresholds
```

See `config/media.php` for grace periods and batch sizes.

## Frontend (Day 9)

| Area | Location |
| --- | --- |
| Admin gallery UI | `frontend/src/features/catalog/media/` |
| Product wizard step | `ProductFormPage` → **Media** (edit mode only) |
| Variant entry | `ProductVariantsSection` → **Media** per row |
| API client multipart | `apiFormRequest` in `lib/api-client.ts`, `media-api.ts` |
| Storefront rendering | `ResponsiveProductImage`, `ProductGallery`, `features/storefront/media/` |
| Placeholder | `/public/storefront/product-placeholder.svg` |

Permissions: view with `catalog.view`, mutate with `catalog.manage` (no `catalog.publish`).

## Readiness

Product readiness (Day 7+) can require a primary **ready** image before activation. Variant galleries inherit product media when empty.

## Related docs

- [ADR 0004: Local media storage and async derivatives](adr/0004-local-media-storage-and-async-derivatives.md)
- [Catalog domain README](../backend/app/Domains/Catalog/README.md)
- [Storefront design](storefront-design.md)
