# Outdoor Commerce

Local development foundation for an outdoor commerce platform covering hunting, fishing, and outdoor equipment.

Day 1 provides reproducible project structure, API and storefront skeletons, Docker Compose services, quality tooling, and documentation. Day 2 adds modular monolith boundaries, architecture tests, and API correlation IDs. Day 3 adds GitHub Actions CI as the required quality gate. Business features (catalog, orders, payments, seasons, maps, recommendations) are intentionally not implemented yet.

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
- [docs/api-conventions.md](docs/api-conventions.md)

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
```

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
```

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
# Outdoor-Commerce
