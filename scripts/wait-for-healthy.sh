#!/usr/bin/env sh
set -eu

if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <service> [service...]" >&2
  exit 1
fi

MAX_ATTEMPTS="${WAIT_MAX_ATTEMPTS:-60}"
SLEEP_SECONDS="${WAIT_SLEEP_SECONDS:-2}"

for service in "$@"; do
  attempt=1
  echo "Waiting for ${service} to become healthy..."

  while [ "${attempt}" -le "${MAX_ATTEMPTS}" ]; do
    cid="$(docker compose ps -q "${service}" 2>/dev/null || true)"

    if [ -n "${cid}" ]; then
      status="$(docker inspect --format='{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "${cid}" 2>/dev/null || true)"

      if [ "${status}" = "healthy" ]; then
        echo "${service} is healthy."
        break
      fi

      # Services without a healthcheck are ready once running
      if [ "${status}" = "running" ]; then
        has_health="$(docker inspect --format='{{if .State.Health}}yes{{else}}no{{end}}' "${cid}" 2>/dev/null || echo no)"
        if [ "${has_health}" = "no" ]; then
          echo "${service} is running."
          break
        fi
      fi
    fi

    if [ "${attempt}" -eq "${MAX_ATTEMPTS}" ]; then
      echo "Timed out waiting for ${service} (last status: ${status:-unknown})." >&2
      docker compose ps "${service}" >&2 || true
      exit 1
    fi

    attempt=$((attempt + 1))
    sleep "${SLEEP_SECONDS}"
  done
done
