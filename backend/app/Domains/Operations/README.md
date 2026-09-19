# Operations module

## Responsibility

Admin operational tooling and privileged-action audit trails.

## Data owned

- `audit_logs` (append-only)

## Public contracts

Other modules may call:

- `App\Domains\Operations\Actions\RecordAuditEventAction`
- `App\Domains\Operations\DTOs\AuditEventData`
- `App\Domains\Operations\Enums\AuditEvent`
- `App\Domains\Operations\Queries\AuditLogListQuery` (HTTP/admin use)

Do not import `Models\AuditLog` from other domains — use Actions/Queries.

## May depend on

Shared only (audit rows store actor ids, not Identity models).

## Explicitly outside this module

Customer cart/checkout UX; catalog/order business rules.

## Day 5

Implements immutable audit logging for administrator provisioning, role/status changes, access denials, and permission synchronization.
