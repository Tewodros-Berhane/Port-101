#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

KEEP_DATABASE=0
CLEANUP_ARTIFACTS=0
DRILL_DB_NAME="${DRILL_DB_NAME:-}"
DB_NAME_OVERRIDE="${DB_NAME:-${DB_DATABASE:-}}"
DB_USER_OVERRIDE="${DB_USER:-${DB_USERNAME:-}}"
DB_PASSWORD_OVERRIDE="${DB_PASSWORD:-}"
DB_HOST_OVERRIDE="${DB_HOST:-}"
DB_PORT_OVERRIDE="${DB_PORT:-}"

dotenv_value() {
  local key="$1"
  local env_file="${REPO_ROOT}/.env"

  if [[ ! -f "${env_file}" ]]; then
    return 0
  fi

  local line
  line="$(grep -E "^${key}=" "${env_file}" | tail -n 1 || true)"
  line="${line#*=}"
  line="${line%$'\r'}"
  line="${line%\"}"
  line="${line#\"}"
  line="${line%\'}"
  line="${line#\'}"

  printf '%s' "${line}"
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --db-name)
      DB_NAME_OVERRIDE="$2"
      shift 2
      ;;
    --db-user)
      DB_USER_OVERRIDE="$2"
      shift 2
      ;;
    --db-password)
      DB_PASSWORD_OVERRIDE="$2"
      shift 2
      ;;
    --db-host)
      DB_HOST_OVERRIDE="$2"
      shift 2
      ;;
    --db-port)
      DB_PORT_OVERRIDE="$2"
      shift 2
      ;;
    --drill-db-name)
      DRILL_DB_NAME="$2"
      shift 2
      ;;
    --keep-database)
      KEEP_DATABASE=1
      shift
      ;;
    --cleanup-artifacts)
      CLEANUP_ARTIFACTS=1
      shift
      ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 1
      ;;
  esac
done

DB_NAME_OVERRIDE="${DB_NAME_OVERRIDE:-$(dotenv_value DB_DATABASE)}"
DB_USER_OVERRIDE="${DB_USER_OVERRIDE:-$(dotenv_value DB_USERNAME)}"
DB_PASSWORD_OVERRIDE="${DB_PASSWORD_OVERRIDE:-$(dotenv_value DB_PASSWORD)}"
DB_HOST_OVERRIDE="${DB_HOST_OVERRIDE:-$(dotenv_value DB_HOST)}"
DB_PORT_OVERRIDE="${DB_PORT_OVERRIDE:-$(dotenv_value DB_PORT)}"

DB_HOST_OVERRIDE="${DB_HOST_OVERRIDE:-127.0.0.1}"
DB_PORT_OVERRIDE="${DB_PORT_OVERRIDE:-5432}"

if [[ -z "${DB_NAME_OVERRIDE}" || -z "${DB_USER_OVERRIDE}" ]]; then
  echo "DB_DATABASE/DB_NAME and DB_USERNAME/DB_USER must be available via parameters, environment, or .env." >&2
  exit 1
fi

TIMESTAMP="$(date -u +%Y%m%d-%H%M%S)"
SUFFIX="$(date -u +%H%M%S)-$RANDOM"
WORKSPACE="${REPO_ROOT}/storage/app/restore-drills/${TIMESTAMP}-${SUFFIX}"
BACKUP_DATABASE_DIR="${WORKSPACE}/backups/database"
BACKUP_STORAGE_DIR="${WORKSPACE}/backups/storage"
RESTORE_ROOT="${WORKSPACE}/restore-root"
LOGS_DIR="${WORKSPACE}/logs"

mkdir -p "${BACKUP_DATABASE_DIR}" "${BACKUP_STORAGE_DIR}" "${RESTORE_ROOT}" "${LOGS_DIR}"

DB_SLUG="$(printf '%s' "${DB_NAME_OVERRIDE}" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9_]/_/g')"
DB_SLUG="${DB_SLUG:0:40}"

if [[ -z "${DRILL_DB_NAME}" ]]; then
  DRILL_DB_NAME="${DB_SLUG}_restore_${SUFFIX//[^a-zA-Z0-9]/}"
fi

cleanup() {
  if [[ "${KEEP_DATABASE}" -eq 0 ]]; then
    PGPASSWORD="${DB_PASSWORD_OVERRIDE}" dropdb \
      --if-exists \
      --host "${DB_HOST_OVERRIDE}" \
      --port "${DB_PORT_OVERRIDE}" \
      --username "${DB_USER_OVERRIDE}" \
      "${DRILL_DB_NAME}" >/dev/null 2>&1 || true
  fi

  if [[ "${CLEANUP_ARTIFACTS}" -eq 1 ]]; then
    rm -rf "${WORKSPACE}"
  fi
}

on_error() {
  echo "Restore drill failed. Workspace retained at ${WORKSPACE}" >&2
  echo "Inspect logs under ${LOGS_DIR}" >&2
}

trap on_error ERR
trap cleanup EXIT

cd "${REPO_ROOT}"

echo "Creating disposable restore drill workspace at ${WORKSPACE}"

DB_DUMP_PATH="$(
  DB_NAME="${DB_NAME_OVERRIDE}" \
  DB_USER="${DB_USER_OVERRIDE}" \
  DB_PASSWORD="${DB_PASSWORD_OVERRIDE}" \
  DB_HOST="${DB_HOST_OVERRIDE}" \
  DB_PORT="${DB_PORT_OVERRIDE}" \
  OUTPUT_DIR="${BACKUP_DATABASE_DIR}" \
  "${SCRIPT_DIR}/backup-postgres.sh"
)"

STORAGE_ARCHIVE_PATH="$(
  OUTPUT_DIR="${BACKUP_STORAGE_DIR}" \
  "${SCRIPT_DIR}/backup-storage.sh"
)"

PGPASSWORD="${DB_PASSWORD_OVERRIDE}" createdb \
  --host "${DB_HOST_OVERRIDE}" \
  --port "${DB_PORT_OVERRIDE}" \
  --username "${DB_USER_OVERRIDE}" \
  "${DRILL_DB_NAME}"

DB_NAME="${DRILL_DB_NAME}" \
DB_USER="${DB_USER_OVERRIDE}" \
DB_PASSWORD="${DB_PASSWORD_OVERRIDE}" \
DB_HOST="${DB_HOST_OVERRIDE}" \
DB_PORT="${DB_PORT_OVERRIDE}" \
"${SCRIPT_DIR}/restore-postgres.sh" "${DB_DUMP_PATH}" >/dev/null

"${SCRIPT_DIR}/restore-storage.sh" "${STORAGE_ARCHIVE_PATH}" "${RESTORE_ROOT}" >/dev/null

export DB_CONNECTION="pgsql"
export DB_DATABASE="${DRILL_DB_NAME}"
export DB_USERNAME="${DB_USER_OVERRIDE}"
export DB_PASSWORD="${DB_PASSWORD_OVERRIDE}"
export DB_HOST="${DB_HOST_OVERRIDE}"
export DB_PORT="${DB_PORT_OVERRIDE}"
export LOCAL_FILESYSTEM_ROOT="${RESTORE_ROOT}/storage/app/private"
export PUBLIC_FILESYSTEM_ROOT="${RESTORE_ROOT}/storage/app/public"
export BACKUP_ATTACHMENTS_DISK="local"
export BACKUP_DATABASE_DUMP_DIR="${BACKUP_DATABASE_DIR}"
export BACKUP_STORAGE_ARCHIVE_DIR="${BACKUP_STORAGE_DIR}"

php artisan optimize:clear >/dev/null
php artisan migrate --force >/dev/null
php artisan platform:operations:heartbeat >/dev/null
php artisan ops:recovery:smoke-check --json > "${LOGS_DIR}/recovery-smoke-check.json"
php artisan ops:deploy:smoke-check --json --require-heartbeat > "${LOGS_DIR}/deploy-smoke-check.json"

echo "Restore drill completed successfully."
echo "Drill database: ${DRILL_DB_NAME}"
echo "Workspace: ${WORKSPACE}"
echo "Recovery result: ${LOGS_DIR}/recovery-smoke-check.json"
echo "Deploy result: ${LOGS_DIR}/deploy-smoke-check.json"
