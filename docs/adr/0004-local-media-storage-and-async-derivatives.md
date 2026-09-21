# ADR 0004: Local media storage and async derivatives

## Status

Accepted — Day 9

## Context

The catalog needs product and variant images with responsive derivatives (WebP + fallbacks, multiple presets) without introducing S3 or a CDN in the first iteration. Uploads must stay private; only optimized renditions are published. Processing can take seconds and must not block HTTP requests.

## Decision

1. **Two logical disks** (see `config/media.php`):
   - `media_private` for originals under `storage/app/private/media`
   - `media_public` for derivatives under `storage/app/public/media`, served via `storage:link`

2. **Async pipeline**: `UploadMediaAction` persists the original and dispatches `ProcessMediaAsset` on the `media` queue. Admin uploads return **202** with attachment metadata; clients poll `GET /admin/media/{asset}/status` or refresh the gallery.

3. **Manifest boundary**: `MediaPresentationService` is the only serializer for attachment JSON. It never emits original paths or private disk names.

4. **Presets and formats**: Fixed preset ladder (thumbnail → zoom). WebP + JPEG required; AVIF optional via config. PNG preserved when source is PNG.

5. **Security**: MIME sniffing and dimension limits in `MediaUploadValidator`; dangerous extensions blocked. Failed validation can quarantine assets.

6. **Frontend**: Admin UI in Next.js; storefront consumes the same manifest shape via `ResponsiveMedia` (Day 12 public API).

## Consequences

- Operators must run `php artisan queue:work --queue=media,default` and `storage:link` in each environment.
- Backups must include both private originals and public derivatives.
- Moving to S3 later means swapping disk drivers and URL generation in `MediaUrlService`, not changing the manifest contract.
- Orphan cleanup and stuck recovery are operational concerns (`media:cleanup-orphans`, `media:recover-stuck`).

## Alternatives considered

- **Synchronous processing in the request**: rejected — timeouts and poor UX on large images.
- **Single public disk for originals**: rejected — exposes unprocessed uploads and complicates security review.
- **Storing only one derivative size**: rejected — storefront and admin need distinct presets.
