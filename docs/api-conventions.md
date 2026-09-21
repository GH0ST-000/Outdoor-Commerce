# API Conventions

Day 2 defines the transport conventions future API endpoints must follow. Only the correlation ID middleware and a minimal error-meta foundation are implemented today.

## Versioning

- Public JSON APIs live under `/api` today.
- New feature endpoints should be added under `/api/v1/...` using controllers in `App\Http\Controllers\Api\V1`.
- The Day 1 health endpoint `GET /api/health` remains stable and unversioned.

## Correlation ID

Every API request must resolve a correlation ID:

- Incoming header: `X-Request-ID`
- Accepted format: UUID (versions 1–8), case-insensitive
- Invalid, missing, or excessively long values are replaced with a newly generated UUID
- The resolved ID is stored on the request attribute `correlation_id`
- The same value is returned on the response header `X-Request-ID`
- The ID is added to Laravel’s logging context as `request_id` for the duration of the request

Clients and support tooling should treat `X-Request-ID` as the support reference for a single request.

## Success responses

Prefer API Resources under `App\Http\Resources\Api\V1` for object payloads. Keep field names stable and documented. Do not return Eloquent models directly from controllers.

## Error responses

Future API endpoints should converge on this shape:

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "The submitted data is invalid.",
    "details": {
      "field": ["The field is required."]
    }
  },
  "meta": {
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

### Rules

- `error.code` is a stable, machine-readable string (for example `VALIDATION_FAILED`, `UNAUTHORIZED`, `NOT_FOUND`).
- `error.message` is human-readable and may be localized later.
- `error.details` is optional and must only contain safe validation or field messages.
- Production responses must never expose stack traces, SQL, file paths, secrets, or raw provider payloads.
- `meta.request_id` must match the request correlation ID whenever available.

Day 2 does not replace Laravel’s full exception renderer. Controllers and future handlers should attach `meta.request_id` when returning structured errors, and middleware already ensures the response header is present.

## Validation

- Use Form Requests under `App\Http\Requests\Api\V1` for non-trivial input.
- Transport validation stays in Form Requests; domain invariants stay in Actions/Services.
- Form Requests must not perform database-changing side effects.

## Authorization

- Policies and Gates own authorization for protected resources.
- Deny by default.
- Frontend visibility is never a substitute for server authorization.
- Day 5 RBAC details: [authorization.md](authorization.md).

## Controllers

Controllers must stay thin: validate, authorize, map to a `*Data` DTO when needed, call one Action or Query, return a Resource or small stable JSON payload.

## Authentication endpoints (Day 4)

Base path: `/api/v1/auth`

| Method | Path | Auth |
| --- | --- | --- |
| POST | `/register` | Guest |
| POST | `/login` | Guest |
| POST | `/logout` | Sanctum + active |
| GET | `/me` | Sanctum + active |
| POST | `/forgot-password` | Guest |
| POST | `/reset-password` | Guest |
| GET | `/email/verify/{id}/{hash}` | Signed |
| POST | `/email/verification-notification` | Sanctum + active |

See [authentication.md](authentication.md) for flows, CSRF, rate limits, and cookie configuration.

## Admin endpoints (Day 5)

Base path: `/api/v1/admin` — requires Sanctum, active user, and `admin.access`. See [authorization.md](authorization.md).

| Method | Path | Permission |
| --- | --- | --- |
| GET | `/context` | `admin.access` |
| GET | `/users` | `users.view` |
| GET | `/users/{user}` | `users.view` |
| PATCH | `/users/{user}/status` | `users.status.manage` |
| PUT | `/users/{user}/roles` | `users.roles.manage` |
| GET | `/roles` | `roles.view` |
| GET | `/audit-logs` | `audit-logs.view` |
| GET | `/audit-logs/{auditLog}` | `audit-logs.view` |
| GET | `/catalog/options/categories` | `catalog.view` |
| GET | `/catalog/options/brands` | `catalog.view` |
| GET | `/products` | `catalog.view` |
| POST | `/products` | `catalog.manage` |
| GET | `/products/{product}` | `catalog.view` |
| PATCH | `/products/{product}` | `catalog.manage` |
| PATCH | `/products/{product}/status` | `catalog.manage` (+ `catalog.publish` to activate) |
| GET | `/products/{product}/readiness` | `catalog.view` |
| DELETE | `/products/{product}` | `catalog.manage` (archive) |
| POST | `/products/{product}/restore` | `catalog.manage` |

Product Core details: [catalog-product-core.md](catalog-product-core.md).

## Public catalog (Day 12)

Unauthenticated read-only routes live under `/api/v1/catalog`. See [public-catalog.md](public-catalog.md) and [openapi/public-catalog.yaml](openapi/public-catalog.yaml).

Default pagination: 10. Default currency: GEL. Price inputs/outputs use minor units. Attribute groups use AND semantics. Values inside one group use OR semantics. Non-public products return `CATALOG_PRODUCT_NOT_FOUND` (404), never 403.
