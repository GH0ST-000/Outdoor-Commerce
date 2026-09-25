# Outdoor Commerce

Local development foundation for an outdoor commerce platform covering hunting, fishing, and outdoor equipment.

Day 1 provides reproducible project structure, API and storefront skeletons, Docker Compose services, quality tooling, and documentation. Day 2 adds modular monolith boundaries, architecture tests, and API correlation IDs. Day 3 adds GitHub Actions CI as the required quality gate. Day 4 adds Laravel Sanctum cookie authentication for customers. Day 5 adds RBAC, admin APIs, a protected Next.js admin shell, and audit logging. Order creation, payments, seasons, maps, and recommendations remain later phases.

**This Day 1 configuration is for local development only. It is not production-ready.** Replace every local credential before any shared or production deployment. Never commit real secrets.

## Technical stack

| Layer | Technology |
| --- | --- |
| Backend | Laravel REST API (PHP 8.4+) |
| Frontend | Next.js (App Router), React, TypeScript |
| Database | MySQL 8 |
| Cache / queues | Redis |
| Search | Meilisearch |
| Email (local) | Mailpit |
| File storage (Day 1) | Local Laravel disk |
| Orchestration | Docker Compose |
| Production target | Hostinger KVM VPS (later phases) |

## Repository structure

```text
project-root/
├── backend/          Laravel API (modular Domains under app/Domains)
├── frontend/         Next.js storefront
├── docker/           Development Dockerfiles
├── docs/             Architecture, ADR, API conventions
├── scripts/          Setup helpers
├── .github/          PR and issue templates
├── docker-compose.yml
├── Makefile
├── .env.example
└── README.md
```

Architecture docs:

- [docs/architecture.md](docs/architecture.md)
- [docs/adr/0001-modular-monolith.md](docs/adr/0001-modular-monolith.md)
- [docs/adr/0002-product-core-boundaries.md](docs/adr/0002-product-core-boundaries.md)
- [docs/adr/0003-variant-combination-identity-and-sku.md](docs/adr/0003-variant-combination-identity-and-sku.md)
- [docs/api-conventions.md](docs/api-conventions.md)
- [docs/authentication.md](docs/authentication.md)
- [docs/authorization.md](docs/authorization.md)
- [docs/catalog-product-core.md](docs/catalog-product-core.md)
- [docs/catalog-variants.md](docs/catalog-variants.md)
- [docs/ci.md](docs/ci.md)
- [docs/cart.md](docs/cart.md)
- [docs/adr/0012-persistent-server-authoritative-cart.md](docs/adr/0012-persistent-server-authoritative-cart.md)
- [docs/checkout.md](docs/checkout.md)
- [docs/orders.md](docs/orders.md)
- [docs/payments.md](docs/payments.md)
- [docs/payments-bog.md](docs/payments-bog.md)
- [docs/adr/0014-atomic-order-creation.md](docs/adr/0014-atomic-order-creation.md)
- [docs/adr/0015-provider-agnostic-payment-core.md](docs/adr/0015-provider-agnostic-payment-core.md)
- [docs/adr/0016-bog-payment-manager.md](docs/adr/0016-bog-payment-manager.md)
- [docs/fulfillment.md](docs/fulfillment.md)
- [docs/adr/0017-carrier-neutral-fulfillment.md](docs/adr/0017-carrier-neutral-fulfillment.md)
- [docs/species.md](docs/species.md)
- [docs/adr/0018-species-facts-vs-legal-rules.md](docs/adr/0018-species-facts-vs-legal-rules.md)
- [docs/legal.md](docs/legal.md)
- [docs/adr/0019-versioned-legal-rules.md](docs/adr/0019-versioned-legal-rules.md)

Backend and frontend dependencies stay isolated (`backend/vendor`, `frontend/node_modules`).

## Required local software

Primary workflow is Docker — you do not need Laravel Herd (or any other local PHP stack).

- Docker Desktop (or Docker Engine) with Compose v2
- Make
- Git

Optional host-side tools (Homebrew preferred; avoid Herd on `PATH`):

- PHP 8.4+ (`brew install php`)
- Composer 2 (`brew install composer` — use `/opt/homebrew/bin/composer` or `/usr/local/bin/composer`)
- Node.js 22+ and npm (`brew install node`)

If `which composer` points under `~/Library/Application Support/Herd/`, Herd is shadowing Homebrew. Either remove Herd from `PATH`, or call Composer by absolute path:

```bash
/usr/local/bin/composer install
# or
/opt/homebrew/bin/composer install
```

## First-time setup

```bash
cp .env.example .env   # or rely on make setup
make setup
```

`make setup` is idempotent. It will:

1. Create `.env`, `backend/.env`, and `frontend/.env` from examples only when missing
2. Build containers
3. Start infrastructure and applications
4. Install dependencies inside containers when needed
5. Generate a Laravel `APP_KEY` when missing
6. Run migrations
7. Print local URLs

## Start and stop

```bash
make up        # start all services
make down      # stop services (keeps named volumes)
make restart   # restart services
make logs      # follow logs
make ps        # container status
```

## Local service URLs

| Service | URL |
| --- | --- |
| Frontend | http://localhost:3000 |
| Backend API | http://localhost:8000 |
| Mailpit UI | http://localhost:8025 |
| Meilisearch | http://localhost:7700 |

## Customer authentication (Day 4)

Storefront routes:

- http://localhost:3000/login
- http://localhost:3000/register
- http://localhost:3000/forgot-password
- http://localhost:3000/account

API auth uses Laravel Sanctum cookies. See [docs/authentication.md](docs/authentication.md).

Local verification and password-reset emails appear in **Mailpit** at http://localhost:8025. Production mail providers are not configured in Day 4.

## Administration (Day 5)

Admin console: http://localhost:3000/admin

```bash
cd backend
php artisan access-control:sync
php artisan admin:create
```

See [docs/authorization.md](docs/authorization.md) for roles, permissions, audit logs, and last-active-admin protection.

| Backend API | http://localhost:8000 |
| Backend health | http://localhost:8000/api/health |
| Frontend health | http://localhost:3000/api/health |
| Mailpit UI | http://localhost:8025 |
| Meilisearch | http://localhost:7700 |
| MySQL (host tools) | localhost:3306 |
| Redis (host tools) | localhost:6379 |

Default local credentials are placeholders (`outdoor` / `secret`, Meilisearch `masterKey_change_me`). Change them for any non-local environment.

## Backend tests

```bash
make test-backend
# or
docker compose exec backend composer test
# host-side (uses isolated sqlite in-memory via phpunit.xml):
cd backend && composer test
# sequential (debugging):
cd backend && composer test:sequential
```

`composer test` runs Pest with `--parallel`. Each worker gets its own SQLite memory database locally, or `outdoor_test_test_{N}` on MySQL. Requires PHP `pcntl`. If a run looks flaky, use `composer test:sequential`.

## Frontend tests

```bash
make test-frontend
# or
docker compose exec frontend npm run test:run
# host-side:
cd frontend && npm run test:run
```

## Quality checks

```bash
make quality
```

Backend quality runs Pint (format check), PHPStan/Larastan, and Pest.

Frontend quality runs Prettier check, ESLint, TypeScript, Vitest, and a production build.

## Mailpit

Open http://localhost:8025 to inspect messages sent by the backend during local development. SMTP is available at `mailpit:1025` inside Compose (host port `1025`).

## File storage

Day 1 uses Laravel’s local disk (`FILESYSTEM_DISK=local`). Object storage (for example MinIO/S3) can be added in a later phase when uploads are needed.

## Laravel migrations

```bash
make migrate
# or
docker compose exec backend php artisan migrate
php artisan access-control:sync
```

Administrators are created with `php artisan admin:create` — never via production seeders.

## Environment-variable rules

- Track only `*.env.example` files. Never commit real `.env` files.
- Root `.env` feeds Docker Compose.
- `backend/.env` is used by Laravel (Compose also injects runtime values).
- `frontend/.env` separates:
  - **Public:** `NEXT_PUBLIC_*` only
  - **Server-only:** `BACKEND_INTERNAL_URL` and other non-public vars
- Do not expose Meilisearch master keys to browser code.
- Do not log environment variables.
- Disable Laravel debug outside local development (`APP_DEBUG=false`).

## Shell access

```bash
make backend-shell
make frontend-shell
```

## Cleanup

```bash
make clean           # stop containers; keep named volumes
make clean-volumes   # prints the explicit destructive command
```

`make clean` never deletes source code. Deleting database/object-store volumes requires an explicit destructive Compose command (`docker compose down -v`).

## Continuous integration

GitHub Actions runs the quality gate on pull requests and pushes to `main` / `develop` / `master`. See [docs/ci.md](docs/ci.md).

Local equivalents:

```bash
make test
make quality
make ci
```

Configure branch protection to require the **`ci-success`** check before merge.


See [docs/conventional-commits.md](docs/conventional-commits.md).

Examples:

```text
feat: add product catalog
fix: prevent duplicate order creation
test: cover inventory reservation
docs: document local setup
chore: configure development environment
```

## Architecture notes

See [docs/architecture.md](docs/architecture.md).

## Troubleshooting

| Problem | What to try |
| --- | --- |
| Port already in use | Change publish ports in `.env` (`FRONTEND_PUBLISH_PORT`, `BACKEND_PUBLISH_PORT`, etc.) |
| Backend unhealthy | `docker compose logs backend`; confirm MySQL/Redis healthy; run `make migrate` |
| Frontend cannot reach API | Check `NEXT_PUBLIC_API_URL` (browser) vs `BACKEND_INTERNAL_URL` (server) |
| Permission errors in containers | Set `HOST_UID` / `HOST_GID` to your user ids and rebuild |
| Composer/npm missing in container | `docker compose exec backend composer install` / `docker compose exec frontend npm install` |
| `composer` prints PHP 8.5 deprecations / crashes | You are likely using Herd’s Composer. Use Docker (`make setup`) or Homebrew Composer at `/usr/local/bin/composer` / `/opt/homebrew/bin/composer` |
| Stale containers | `make down && make up` |
| Need a fresh database | Explicitly run `docker compose down -v` then `make setup` |

## Security reminders

- Never commit credentials or production keys.
- Never place secrets in Dockerfiles.
- Never expose server-only variables to Next.js client bundles.
- Do not publish MySQL or Redis publicly in production.
- Local defaults must be replaced before Hostinger/production deployment.
