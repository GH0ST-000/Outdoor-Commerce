# Authentication

Day 4 introduces **Laravel Sanctum stateful SPA authentication** for customers.

## Why Sanctum cookies (not JWT)

- The browser never stores access tokens in `localStorage` / `sessionStorage`.
- Session cookies are `HttpOnly` and managed by Laravel.
- CSRF protection stays enabled via Sanctum’s `/sanctum/csrf-cookie` flow.
- Laravel remains the only authentication authority; Next.js route guards are UX only.

## Architecture

```text
Next.js (localhost:3000)
  → GET /sanctum/csrf-cookie (credentials)
  → POST /api/v1/auth/* with X-XSRF-TOKEN + session cookie
Laravel Identity domain Actions
  → MySQL users / addresses / password_reset_tokens
  → Redis or array sessions
  → Mailpit (local) for verification + reset mail
```

Domain code lives under `App\Domains\Identity`. HTTP adapters live under `App\Http\Controllers\Api\V1\Auth`.

Compatibility bridge: `App\Models\User` extends `App\Domains\Identity\Models\User`.

## Registration behavior

`POST /api/v1/auth/register` creates an **active but unverified** customer, hashes the password, sends a verification email, dispatches `CustomerRegistered`, **logs the user in**, and regenerates the session. Response: `201` + `AuthenticatedUserResource`.

## Login / logout / me

| Endpoint | Notes |
| --- | --- |
| `POST /api/v1/auth/login` | Generic `INVALID_CREDENTIALS` for unknown email, bad password, or disabled account |
| `POST /api/v1/auth/logout` | Requires auth; invalidates session; regenerates CSRF token; `204` |
| `GET /api/v1/auth/me` | Requires auth + active status |

## CSRF

1. Frontend calls `GET {BACKEND_ORIGIN}/sanctum/csrf-cookie` with credentials.
2. Mutations send `X-XSRF-TOKEN` from the `XSRF-TOKEN` cookie.
3. On HTTP `419`, the client refreshes the CSRF cookie and retries **once**.

## Session / cookie / CORS / Sanctum

Local defaults (see `backend/.env.example`):

- `SESSION_DRIVER=redis` (tests: `array`)
- `SESSION_DOMAIN=null`
- `SESSION_SECURE_COOKIE=false` (HTTP local)
- `SESSION_SAME_SITE=lax`
- `SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1,127.0.0.1:3000`
- `CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000`
- `supports_credentials=true` (no `*` origin)

Production expectations (configure via env, do not hardcode domains in code):

- `SESSION_SECURE_COOKIE=true`
- Shared parent `SESSION_DOMAIN` when using `example.ge` + `api.example.ge`
- Exact frontend origins in CORS and Sanctum stateful domains

## Password policy

Minimum 12 characters, mixed case, and a number. Compromised-password checks (`uncompromised`) run only when `APP_ENV=production`.

## Rate limits

| Limiter | Limit |
| --- | --- |
| `auth.login` | 5 / minute per normalized email + IP |
| `auth.register` | 5 / minute per IP |
| `auth.forgot-password` | 3 / minute per email + IP |
| `auth.reset-password` | 5 / minute per IP |
| `auth.verification-resend` | 3 / minute per user + IP |

## Email verification

1. Registration sends a signed API URL (`api.v1.auth.email.verify`).
2. Laravel validates signature + hash, marks verified (idempotent), dispatches events.
3. Browser redirects to `{FRONTEND_URL}/verify-email?status=success|already`.
4. Authenticated resend: `POST /api/v1/auth/email/verification-notification` → `202` (generic).

## Password reset

1. `POST /api/v1/auth/forgot-password` always returns the same `202` message.
2. Email link opens `{FRONTEND_URL}/reset-password?token=...&email=...`.
3. `POST /api/v1/auth/reset-password` validates token, enforces password policy, rotates `remember_token`, invalidates the used token.
4. If the resetting user is currently authenticated, that session is invalidated.

## User status

`UserStatus`: `active` | `disabled`. Verification is separate (`email_verified_at`). Disabled users cannot log in and are rejected on protected routes with a generic unauthorized response.

## Addresses

`addresses` table + `Address` model prepare checkout later. Ownership is via `user_id` FK. No public address CRUD in Day 4.

## Frontend

Feature module: `frontend/src/features/auth/`

Routes: `/login`, `/register`, `/forgot-password`, `/reset-password`, `/verify-email`, `/account`

`AuthProvider` loads `/api/v1/auth/me` on boot. `AuthGuard` hides private content until auth resolves.

Public env: `NEXT_PUBLIC_API_URL`, `NEXT_PUBLIC_BACKEND_URL`, `NEXT_PUBLIC_APP_URL`. Never put secrets in `NEXT_PUBLIC_*`.

## Security boundaries

- Backend authorizes every protected endpoint.
- Frontend guards are not security controls.
- Passwords, reset tokens, and session IDs are never logged or returned in JSON.
- Failed login responses do not reveal whether an email exists.

## Day 4 limitations / Day 5 note

Day 4 did not include admin roles or MFA.

Day 5 adds RBAC and admin authorization on the same Sanctum cookie model. See [authorization.md](authorization.md). MFA remains future work.

