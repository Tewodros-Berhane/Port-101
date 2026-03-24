#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

DB_NAME="${DB_NAME:-${DB_DATABASE:-}}"
DB_USER="${DB_USER:-${DB_USERNAME:-}}"
DB_PASSWORD="${DB_PASSWORD:-${DB_PASSWORD:-}}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
OUTPUT_DIR="${OUTPUT_DIR:-${REPO_ROOT}/storage/app/backups/database}"
TIMESTAMP="${TIMESTAMP:-$(date -u +%Y%m%d-%H%M%S)}"

if [[ -z "${DB_NAME}" || -z "${DB_USER}" ]]; then
  echo "DB_NAME/DB_DATABASE and DB_USER/DB_USERNAME must be set." >&2
  exit 1
fi

mkdir -p "${OUTPUT_DIR}"

OUTPUT_PATH="${OUTPUT_DIR}/${DB_NAME}-${TIMESTAMP}.dump"

export PGPASSWORD="${DB_PASSWORD:-}"

pg_dump \
  --format=custom \
  --no-owner \
  --no-privileges \
  --host "${DB_HOST}" \
  --port "${DB_PORT}" \
  --username "${DB_USER}" \
  --file "${OUTPUT_PATH}" \
  "${DB_NAME}"

echo "${OUTPUT_PATH}"
