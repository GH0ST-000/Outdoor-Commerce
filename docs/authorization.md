# Authorization

Day 5 introduces role-based access control (RBAC) with granular permissions, a protected admin API, a Next.js admin shell, safe administrator provisioning, and append-only audit logging.

## Principles

- **Permissions** represent capabilities (`users.view`, `catalog.manage`).
- **Roles** group permissions (`admin`, `catalog-manager`, …).
- Laravel is the authorization authority. Frontend checks only control UX/navigation.
- Authorize by permission or policy — avoid scattered role-name comparisons in business logic.
- Do not authorize by email address.
- Do not accept roles or permissions from registration, profile, or client-trusted storage.
- Deny by default; use least privilege.
- Disabled users remain blocked even when they retain administrative roles.
- Privileged mutations are audited.
- Permission cache is cleared after role/permission synchronization and role assignment.

Authentication answers **who** the user is. Authorization answers **what** they may do.

## Package choice

Uses [`spatie/laravel-permission`](https://github.com/spatie/laravel-permission) `^8.3` (compatible with Laravel 13).

- Guard: `web` (same as Sanctum SPA sessions)
- No global `Gate::before` admin bypass — the `admin` role receives every approved permission explicitly
- Tables: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`

## Centralized names

| Location | Purpose |
| --- | --- |
| `App\Domains\Identity\Enums\Role` | Stable role names |
| `App\Domains\Identity\Enums\Permission` | Stable permission names |
| `App\Domains\Identity\Support\RolePermissionMatrix` | Canonical matrix |
| `frontend/src/features/admin/permissions/permissions.ts` | Frontend mirror (UX only) |

## Initial roles

| Role | Purpose (Day 5) |
| --- | --- |
| `admin` | Full admin console + user/role/audit management |
| `catalog-manager` | Admin shell + catalog/inventory/pricing/recommendations foundation |
| `order-manager` | Admin shell + orders foundation (+ inventory/pricing view for support) |
| `legal-editor` | Admin shell + legal-rules foundation (+ content view) |

`content-manager` is **not** created yet — deferred until content workflows need a dedicated role.

Ordinary customers have **no** administrative role.

## Permission matrix

| Permission | Admin | Catalog Manager | Order Manager | Legal Editor |
| --- | ---: | ---: | ---: | ---: |
| `admin.access` | Yes | Yes | Yes | Yes |
| `users.view` | Yes | No | No | No |
| `users.status.manage` | Yes | No | No | No |
| `users.roles.manage` | Yes | No | No | No |
| `roles.view` | Yes | No | No | No |
| `roles.manage` | Yes | No | No | No |
| `audit-logs.view` | Yes | No | No | No |
| `catalog.view` | Yes | Yes | No | No |
| `catalog.manage` | Yes | Yes | No | No |
| `catalog.publish` | Yes | Yes | No | No |
| `inventory.view` | Yes | Yes | Yes | No |
| `inventory.manage` | Yes | Yes | No | No |
| `pricing.view` | Yes | Yes | Yes | No |
| `pricing.manage` | Yes | Yes | No | No |
| `orders.view` | Yes | No | Yes | No |
| `orders.manage` | Yes | No | Yes | No |
| `legal-rules.view` | Yes | No | No | Yes |
| `legal-rules.manage` | Yes | No | No | Yes |
| `legal-rules.publish` | Yes | No | No | Yes |
| `content.view` | Yes | Yes | No | Yes |
| `content.manage` | Yes | No | No | No |
| `content.publish` | Yes | No | No | No |
| `recommendations.view` | Yes | Yes | No | No |
| `recommendations.manage` | Yes | Yes | No | No |
| `operations.view` | Yes | No | No | No |

### Matrix differences from the product brief

- No `content-manager` role.
- `content.view` granted to catalog-manager and legal-editor for future copy/legal pages.
- `content.manage` / `content.publish` remain admin-only.
- `inventory.view` and `pricing.view` granted to order-manager for order support.
- `roles.manage` is assigned to admin only; Day 5 exposes a **read-only** roles API.

## Synchronization

```bash
php artisan access-control:sync
php artisan access-control:sync --dry-run
```

Behavior:

- Creates missing permissions and known roles
- Syncs each known role’s permission set from `RolePermissionMatrix`
- **Preserves** unknown production roles (does not delete them)
- Clears Spatie permission cache after writes
- Audits successful (non-dry-run) sync as `permission_configuration_synchronized`

## First administrator

```bash
php artisan admin:create
```

Interactive by default. Asks for first name, last name, email, password (hidden), and confirmation. Applies the Day 4 password policy. Never accepts a password CLI argument. Never seeds production admins. Existing active users can be promoted only after confirmation (`--promote` for non-interactive confirmation).

## Last active admin protection

Backend invariant: at least one **active** user with the `admin` role must remain.

Blocked operations return `LAST_ACTIVE_ADMIN_REQUIRED` (HTTP 422):

- Disabling the last active administrator
- Removing the `admin` role from the last active administrator

Enforced inside Actions with row locks — not frontend-only.

## Admin API

Base path: `/api/v1/admin`

Middleware stack: `auth:sanctum` → `EnsureUserIsActive` → `EnsureAdminAccess` (`admin.access`) → endpoint permission.

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

### Stable error codes

| Code | Typical status |
| --- | --- |
| `UNAUTHORIZED` | 401 |
| `ADMIN_ACCESS_REQUIRED` | 403 |
| `PERMISSION_DENIED` | 403 |
| `LAST_ACTIVE_ADMIN_REQUIRED` | 422 |
| `INVALID_ROLE` | 422 |
| `VALIDATION_FAILED` | 422 |

Unauthenticated → **401**. Authenticated but unauthorized → **403**.

## Policies

`App\Domains\Identity\Policies\UserPolicy` gates view / status / roles using permissions. Domain invariants (last active admin) stay in Actions.

## Audit logging

Operations domain (`App\Domains\Operations`):

- Append-only `audit_logs` table
- `RecordAuditEventAction` sanitizes payloads (strips passwords, tokens, cookies, secrets)
- IP addresses stored as HMAC hashes; user-agents truncated
- For role/status changes, audit writes run in the same DB transaction as the mutation
- No update/delete application APIs

Audited events include: `administrator_created`, `administrator_promoted`, `user_status_changed`, `user_roles_changed`, `admin_access_denied`, `permission_configuration_synchronized`.

## Frontend

Protected route group: `/admin/*`

- Loads `/api/v1/admin/context` after session auth
- Permission-aware navigation (Dashboard, Users, Roles, Audit Logs)
- Forbidden page for authenticated users without `admin.access`
- Permissions are **not** stored in `localStorage` as authority

## MFA readiness

Administrator MFA is **not** implemented in Day 5.

Future flow:

```text
password authentication → MFA challenge → fully authenticated administrative session
```

Do not display fake MFA badges or recovery codes. Keep login/authorization extensible for a later challenge.

## Local setup

```bash
php artisan migrate
php artisan access-control:sync
php artisan admin:create
```

Open http://localhost:3000/admin after logging in as the administrator.

## Production setup

1. Run migrations (includes permission + audit tables).
2. Run `php artisan access-control:sync`.
3. Create the first administrator with `php artisan admin:create` (interactive or controlled ops process).
4. Never commit admin passwords or seed production admins.

## Day 5 limitations

- No catalog, order, legal-rule, inventory, or pricing UIs
- Roles matrix is read-only (code-synced)
- No audit export
- No MFA enforcement
- No user deletion

## Day 6 prerequisites

- Catalog domain foundations can rely on `catalog.*` permissions and the admin shell
- Continue denying by default and auditing privileged mutations
