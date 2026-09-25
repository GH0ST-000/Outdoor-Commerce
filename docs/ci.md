# Continuous Integration

This repository uses GitHub Actions as the required quality gate for pull requests and protected-branch pushes.

Workflow file: [`.github/workflows/ci.yml`](../.github/workflows/ci.yml)

## Triggers

- `pull_request` (all PRs)
- `push` to `main`, `develop`, and `master` (if present)
- `workflow_dispatch` (manual runs)

Obsolete runs for the same ref are cancelled via workflow concurrency.

## Permissions

Workflow default:

```yaml
permissions:
  contents: read
```

No package write, PR write, deployment, or admin permissions are granted.

## Jobs

| Job | Responsibility |
| --- | --- |
| `repository-validation` | Lockfiles, env examples, Compose syntax, Composer validate, lock consistency, conflict markers |
| `backend-quality` | Pint, PHPStan/Larastan, architecture tests |
| `backend-tests` | Pest `--parallel` on real MySQL 8.4 + Redis 7 + Meilisearch 1.11; per-worker databases; migrations; health/correlation/architecture coverage |
| `frontend-quality` | Prettier, ESLint (`--max-warnings=0`), TypeScript |
| `frontend-tests` | Vitest |
| `frontend-build` | Next.js production build |
| `docker-validation` | `docker compose config` + backend/frontend image builds (no push) |
| `security-audit` | `composer audit`, `npm audit --audit-level=high`, Gitleaks |
| `ci-success` | Stable required check; fails unless every required job succeeded |

Independent tracks run in parallel after `repository-validation`.

## Runtime versions

Declared in:

- `backend/.php-version` → PHP **8.4**
- `.node-version` → Node.js **22**

Keep local Docker/tooling compatible with these versions.

## Local commands (parity with CI)

```bash
make test       # backend + frontend tests
make quality    # backend quality + frontend format/lint/typecheck/tests
make ci         # local equivalent of CI checks runnable on a developer machine
```

Backend scripts:

```bash
cd backend
composer validate --strict --no-check-publish
composer format:check
composer analyse
composer test:architecture
composer test              # Pest --parallel (default)
composer test:sequential   # single process, for debugging flakes
composer ci
```

Pest `--parallel` uses ParaTest (bundled with Pest 4). Feature tests that use `RefreshDatabase` get a per-worker database (`outdoor_test_test_{N}` on MySQL, isolated `:memory:` SQLite locally). CI grants `outdoor_test` access to those `outdoor_test%` schemas. PHP `pcntl` is required (already enabled on the backend-tests runner).

ParaTest accepts a single path. Filter with one directory or a testsuite:

```bash
cd backend
PAO_DISABLE=true vendor/bin/pest --parallel --compact --testsuite=Feature
PAO_DISABLE=true vendor/bin/pest --parallel --compact tests/Feature/Legal
# cap workers:
PAO_DISABLE=true vendor/bin/pest --parallel --processes=4
```

Frontend scripts:

```bash
cd frontend
npm ci
npm run format:check
npm run lint -- --max-warnings=0
npm run typecheck
npm run test:run
npm run build
```

Design-system documentation is development-only (`/dev/design-system` returns 404 in production). Component and accessibility checks live in Vitest (`jest-axe`), not a separate Storybook or Playwright job.

## Backend test infrastructure (GitHub Actions)

| Setting | Value |
| --- | --- |
| MySQL | `mysql:8.4` service |
| Database | `outdoor_test` |
| User / password | `outdoor_test` / `outdoor_test_ci` (CI-only) |
| Redis | `redis:7-alpine` |
| Cache store | `redis` |
| Mail | `array` |
| Filesystem | `local` |

Tests refuse non-test database names (must contain `test`, or sqlite `:memory:`).

Meilisearch, Mailpit, and MinIO are not started in CI unless a future test requires them.

## Caching

- Composer: key `composer-{os}-php{version}-{composer.lock hash}`
- npm: `actions/setup-node` cache on `frontend/package-lock.json`

Caches never include `.env` or runtime state. Installs always verify lockfiles (`composer install`, `npm ci`).

Docker image builds use BuildKit on the runner. GitHub Actions layer cache (`type=gha`) is intentionally not enabled yet because it requires `actions: write`; Day 3 keeps workflow permissions at `contents: read`.

## Security audits

- Backend: `composer audit` fails on known advisories reported by Composer. A Packagist download failure (for example HTTP 502, Composer exit 100) is retried up to 4 times. Advisory findings (exit 1) are not retried.
- Frontend: `npm audit --audit-level=high` fails on high/critical issues.
- Secrets: Gitleaks (`gitleaks/gitleaks-action`, pinned commit SHA).

### Temporary vulnerability exception process

If a high/critical advisory cannot be fixed immediately:

1. Document package, advisory ID, risk, owner, and expiration date in the PR.
2. Prefer upgrading or replacing the package.
3. Do not add a permanent blanket ignore.
4. Do not auto-fix lockfiles in CI.

## Artifacts

On backend test failure, JUnit XML under `backend/storage/logs/junit.xml` may be uploaded (7-day retention). No env files, secrets, vendor trees, or DB dumps are uploaded.

## Branch protection (configure in GitHub settings)

Recommended for `main` / `develop`:

- Require pull request before merge
- Require approvals
- Dismiss stale reviews on new commits
- Require status check: **`ci-success`**
- Require branches to be up to date
- Require conversation resolution
- Block force pushes
- Block branch deletion
- Restrict direct pushes
- Include administrators in these rules where appropriate

Day 3 does not change repository settings via API—apply these manually.

## Reproducing failures locally

1. Read the failed job name in GitHub Actions.
2. Run the matching local command from the tables above.
3. For MySQL-backed backend tests locally: point `DB_*` at a disposable `*_test` database and Redis, then `php artisan migrate --force && composer test`.
4. For Docker build failures: `docker build -f docker/backend/Dockerfile .` and the frontend Dockerfile equivalently.

## Updating pinned GitHub Actions

- Prefer official `actions/*` major tags (`@v4`, `@v5`) and review release notes on upgrade.
- Third-party actions (for example `shivammathur/setup-php`, `gitleaks/gitleaks-action`) are pinned to a full commit SHA with a version comment.
- When Dependabot opens an Actions PR, verify the SHA and re-run CI before merge.

## Required merge check

Configure branch protection to require the single stable job name:

```text
ci-success
```
