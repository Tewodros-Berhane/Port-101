#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

SOURCE_DB_NAME=""
DB_USER=""
DB_PASSWORD=""
DB_HOST=""
DB_PORT=""
KEEP_SOURCE_DATABASE=0
KEEP_RESTORE_DATABASE=0
CLEANUP_ARTIFACTS=0

get_dotenv_value() {
  local key="$1"
  local env_file="${REPO_ROOT}/.env"

  if [[ ! -f "${env_file}" ]]; then
    return 0
  fi

  local line
  line="$(grep -E "^${key}=" "${env_file}" | tail -n 1 || true)"

  if [[ -z "${line}" ]]; then
    return 0
  fi

  local value="${line#*=}"
  value="${value%\"}"
  value="${value#\"}"
  value="${value%\'}"
  value="${value#\'}"
  printf '%s' "${value}"
}

resolve_value() {
  local fallback=""
  local names=()

  while [[ $# -gt 0 ]]; do
    case "$1" in
      --fallback)
        fallback="$2"
        shift 2
        ;;
      *)
        names+=("$1")
        shift
        ;;
    esac
  done

  local name env_value dotenv_value
  for name in "${names[@]}"; do
    env_value="${!name:-}"
    if [[ -n "${env_value}" ]]; then
      printf '%s' "${env_value}"
      return 0
    fi

    dotenv_value="$(get_dotenv_value "${name}")"
    if [[ -n "${dotenv_value}" ]]; then
      printf '%s' "${dotenv_value}"
      return 0
    fi
  done

  printf '%s' "${fallback}"
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --source-db-name)
      SOURCE_DB_NAME="$2"
      shift 2
      ;;
    --db-user)
      DB_USER="$2"
      shift 2
      ;;
    --db-password)
      DB_PASSWORD="$2"
      shift 2
      ;;
    --db-host)
      DB_HOST="$2"
      shift 2
      ;;
    --db-port)
      DB_PORT="$2"
      shift 2
      ;;
    --keep-source-database)
      KEEP_SOURCE_DATABASE=1
      shift
      ;;
    --keep-restore-database)
      KEEP_RESTORE_DATABASE=1
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

cd "${REPO_ROOT}"

DB_USER="${DB_USER:-$(resolve_value DB_USERNAME DB_USER)}"
DB_PASSWORD="${DB_PASSWORD:-$(resolve_value DB_PASSWORD)}"
DB_HOST="${DB_HOST:-$(resolve_value DB_HOST --fallback 127.0.0.1)}"
DB_PORT="${DB_PORT:-$(resolve_value DB_PORT --fallback 5432)}"

if [[ -z "${DB_USER}" ]]; then
  echo "DB_USERNAME/DB_USER must be available via parameters, environment, or .env." >&2
  exit 1
fi

timestamp="$(date +%Y%m%d-%H%M%S)"
suffix="$(openssl rand -hex 4)"

if [[ -z "${SOURCE_DB_NAME}" ]]; then
  SOURCE_DB_NAME="port101_restore_source_${suffix}"
fi

restore_drill_root="${REPO_ROOT}/storage/app/restore-drills"
mkdir -p "${restore_drill_root}"
before_list="$(find "${restore_drill_root}" -mindepth 1 -maxdepth 1 -type d | sort || true)"

export PGPASSWORD="${DB_PASSWORD}"

dropdb --if-exists --host "${DB_HOST}" --port "${DB_PORT}" --username "${DB_USER}" "${SOURCE_DB_NAME}" >/dev/null 2>&1 || true
createdb --host "${DB_HOST}" --port "${DB_PORT}" --username "${DB_USER}" "${SOURCE_DB_NAME}"

previous_db_connection="${DB_CONNECTION:-}"
previous_db_database="${DB_DATABASE:-}"
previous_db_username="${DB_USERNAME:-}"
previous_db_password="${DB_PASSWORD:-}"
previous_db_host="${DB_HOST:-}"
previous_db_port="${DB_PORT:-}"

restore_env() {
  export DB_CONNECTION="${previous_db_connection}"
  export DB_DATABASE="${previous_db_database}"
  export DB_USERNAME="${previous_db_username}"
  export DB_PASSWORD="${previous_db_password}"
  export DB_HOST="${previous_db_host}"
  export DB_PORT="${previous_db_port}"
}

trap restore_env EXIT

export DB_CONNECTION=pgsql
export DB_DATABASE="${SOURCE_DB_NAME}"
export DB_USERNAME="${DB_USER}"
export DB_PASSWORD="${DB_PASSWORD}"
export DB_HOST="${DB_HOST}"
export DB_PORT="${DB_PORT}"

php artisan config:clear >/dev/null
php artisan migrate --force >/dev/null
php artisan db:seed --class=DatabaseSeeder >/dev/null
php artisan db:seed --class=DemoCompanyWorkflowSeeder >/dev/null

restore_args=(
  --db-name "${SOURCE_DB_NAME}"
  --db-user "${DB_USER}"
  --db-password "${DB_PASSWORD}"
  --db-host "${DB_HOST}"
  --db-port "${DB_PORT}"
)

if [[ "${KEEP_RESTORE_DATABASE}" -eq 1 ]]; then
  restore_args+=(--keep-database)
fi

if [[ "${CLEANUP_ARTIFACTS}" -eq 1 ]]; then
  restore_args+=(--cleanup-artifacts)
fi

"${SCRIPT_DIR}/run-restore-drill.sh" "${restore_args[@]}"

workspace=""
if [[ "${CLEANUP_ARTIFACTS}" -ne 1 ]]; then
  after_list="$(find "${restore_drill_root}" -mindepth 1 -maxdepth 1 -type d | sort || true)"
  workspace="$(comm -13 <(printf '%s\n' "${before_list}") <(printf '%s\n' "${after_list}") | tail -n 1 || true)"
fi

if [[ -n "${workspace}" ]]; then
  "${SCRIPT_DIR}/record-restore-signoff.sh" --workspace "${workspace}"
  echo "Seeded restore sign-off completed successfully."
  echo "Source database: ${SOURCE_DB_NAME}"
  echo "Workspace: ${workspace}"
else
  echo "Restore drill workspace could not be resolved for sign-off." >&2
fi

if [[ "${KEEP_SOURCE_DATABASE}" -ne 1 ]]; then
  dropdb --if-exists --host "${DB_HOST}" --port "${DB_PORT}" --username "${DB_USER}" "${SOURCE_DB_NAME}" >/dev/null 2>&1 || true
fi
