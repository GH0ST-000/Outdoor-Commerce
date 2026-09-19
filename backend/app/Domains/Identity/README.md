# Identity

Owns customer accounts, credentials, sessions, email verification, password reset, roles, and permissions.

## Public surface (other modules)

- `App\Domains\Identity\Models\User`
- `App\Domains\Identity\Enums\UserStatus`
- `App\Domains\Identity\Enums\Role`
- `App\Domains\Identity\Enums\Permission`
- Actions such as `CreateAdministratorAction`, `ChangeUserStatusAction`, `AssignUserRolesAction`
- Domain events: `CustomerRegistered`, `CustomerLoggedIn`, `CustomerEmailVerified`

## Depends on

- `Shared`
- `Operations` public Actions/DTOs/Enums for audit recording (`RecordAuditEventAction`)

## Does not own

Catalog, payments, multi-factor auth (future), storefront UX.
