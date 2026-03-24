#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

INPUT_FILE="${1:-}"
DB_NAME="${DB_NAME:-${DB_DATABASE:-}}"
DB_USER="${DB_USER:-${DB_USERNAME:-}}"
DB_PASSWORD="${DB_PASSWORD:-${DB_PASSWORD:-}}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"

if [[ -z "${INPUT_FILE}" ]]; then
  echo "Usage: restore-postgres.sh <dump-file>" >&2
  exit 1
fi

if [[ ! -f "${INPUT_FILE}" ]]; then
  echo "Dump file not found: ${INPUT_FILE}" >&2
  exit 1
fi

if [[ -z "${DB_NAME}" || -z "${DB_USER}" ]]; then
  echo "DB_NAME/DB_DATABASE and DB_USER/DB_USERNAME must be set." >&2
  exit 1
fi

export PGPASSWORD="${DB_PASSWORD:-}"

pg_restore \
  --clean \
  --if-exists \
  --no-owner \
  --no-privileges \
  --host "${DB_HOST}" \
  --port "${DB_PORT}" \
  --username "${DB_USER}" \
  --dbname "${DB_NAME}" \
  "${INPUT_FILE}"

echo "Restore completed into ${DB_NAME}."
