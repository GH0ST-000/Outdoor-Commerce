#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

fail() {
  echo "repository-validation: $*" >&2
  exit 1
}

echo "==> Checking required directories"
for dir in backend frontend docker docs scripts .github; do
  [[ -d "$dir" ]] || fail "missing directory: $dir"
done

echo "==> Checking required lockfiles and examples"
[[ -f backend/composer.lock ]] || fail "missing backend/composer.lock"
[[ -f frontend/package-lock.json ]] || fail "missing frontend/package-lock.json"
[[ -f .env.example ]] || fail "missing .env.example"
[[ -f backend/.env.example ]] || fail "missing backend/.env.example"
[[ -f frontend/.env.example ]] || fail "missing frontend/.env.example"
[[ -f docker-compose.yml ]] || fail "missing docker-compose.yml"
[[ -f .github/workflows/ci.yml ]] || fail "missing .github/workflows/ci.yml"

echo "==> Checking Docker Compose syntax"
docker compose config -q

echo "==> Validating Composer configuration"
(
  cd backend
  COMPOSER_BIN=""
  for candidate in /usr/local/bin/composer /opt/homebrew/bin/composer; do
    if [[ -x "$candidate" ]]; then
      COMPOSER_BIN="$candidate"
      break
    fi
  done
  if [[ -z "$COMPOSER_BIN" ]]; then
    COMPOSER_BIN="$(command -v composer || true)"
  fi
  [[ -n "$COMPOSER_BIN" ]] || fail "composer executable not found"
  php -d display_errors=0 -d error_reporting=22527 "$COMPOSER_BIN" validate --strict --no-check-publish --no-interaction
)

echo "==> Checking frontend package-lock consistency"
(
  cd frontend
  npm ci --dry-run --ignore-scripts >/dev/null
)

if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "==> Ensuring real .env files are not tracked"
  tracked_env="$(git ls-files -- '.env' '**/.env' ':!:**/.env.example' 2>/dev/null || true)"
  if [[ -n "${tracked_env}" ]]; then
    fail "tracked environment files must not be committed: ${tracked_env}"
  fi

  echo "==> Scanning for merge-conflict markers"
  if git grep -nE '^(<<<<<<<|=======|>>>>>>>)' -- \
    ':!**/node_modules/**' \
    ':!**/vendor/**' \
    ':!**/.next/**' \
    >/tmp/conflict-markers.txt 2>/dev/null; then
    cat /tmp/conflict-markers.txt >&2
    fail "merge conflict markers found"
  fi

  echo "==> Checking for accidental debug artifacts"
  if git ls-files -- '*.log' '.phpunit.result.cache' 2>/dev/null | grep -q .; then
    git ls-files -- '*.log' '.phpunit.result.cache' >&2
    fail "debug or temporary artifacts are tracked"
  fi
else
  echo "==> Skipping git-tracked file checks (not a git repository yet)"
fi

echo "repository-validation: OK"
