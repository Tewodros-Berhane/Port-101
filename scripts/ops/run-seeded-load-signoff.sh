#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

SOURCE_DB_NAME=""
DB_USER=""
DB_PASSWORD=""
DB_HOST=""
DB_PORT=""
HOST="127.0.0.1"
PORT="8011"
COMPANY="demo-company-workflow"
TOKEN_NAME="ops-load-test"
VUS="4"
DURATION="30s"
KEEP_SOURCE_DATABASE=0
KEEP_SERVER_LOGS=0
K6_BIN="${K6_BIN:-}"
VALIDATION_PROFILE="${LOAD_TEST_VALIDATION_PROFILE:-rehearsal}"

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
  value="${value%$'\r'}"
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
    --host)
      HOST="$2"
      shift 2
      ;;
    --port)
      PORT="$2"
      shift 2
      ;;
    --company)
      COMPANY="$2"
      shift 2
      ;;
    --token-name)
      TOKEN_NAME="$2"
      shift 2
      ;;
    --vus)
      VUS="$2"
      shift 2
      ;;
    --duration)
      DURATION="$2"
      shift 2
      ;;
    --k6-bin)
      K6_BIN="$2"
      shift 2
      ;;
    --validation-profile)
      VALIDATION_PROFILE="$2"
      shift 2
      ;;
    --keep-source-database)
      KEEP_SOURCE_DATABASE=1
      shift
      ;;
    --keep-server-logs)
      KEEP_SERVER_LOGS=1
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
  SOURCE_DB_NAME="port101_load_source_${suffix}"
fi

load_test_root="${REPO_ROOT}/storage/app/load-tests"
load_signoff_root="${REPO_ROOT}/storage/app/load-signoffs"
logs_root="${REPO_ROOT}/storage/app/load-test-logs"
mkdir -p "${load_test_root}" "${load_signoff_root}" "${logs_root}"

mapfile -t existing_summaries < <(find "${load_test_root}" -maxdepth 1 -type f -name '*.json' | sort || true)
mapfile -t existing_signoffs < <(find "${load_signoff_root}" -maxdepth 1 -type f -name '*.json' | sort || true)

server_stdout="${logs_root}/seeded-load-${timestamp}-${suffix}.out.log"
server_stderr="${logs_root}/seeded-load-${timestamp}-${suffix}.err.log"
base_url="http://${HOST}:${PORT}"
server_script="${REPO_ROOT}/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
server_working_directory="${REPO_ROOT}/public"

export PGPASSWORD="${DB_PASSWORD}"

dropdb --if-exists --host "${DB_HOST}" --port "${DB_PORT}" --username "${DB_USER}" "${SOURCE_DB_NAME}" >/dev/null 2>&1 || true
createdb --host "${DB_HOST}" --port "${DB_PORT}" --username "${DB_USER}" "${SOURCE_DB_NAME}"

previous_db_connection="${DB_CONNECTION:-}"
previous_db_database="${DB_DATABASE:-}"
previous_db_username="${DB_USERNAME:-}"
previous_db_password="${DB_PASSWORD:-}"
previous_db_host="${DB_HOST:-}"
previous_db_port="${DB_PORT:-}"
previous_app_url="${APP_URL:-}"

restore_env() {
  export DB_CONNECTION="${previous_db_connection}"
  export DB_DATABASE="${previous_db_database}"
  export DB_USERNAME="${previous_db_username}"
  export DB_PASSWORD="${previous_db_password}"
  export DB_HOST="${previous_db_host}"
  export DB_PORT="${previous_db_port}"
  export APP_URL="${previous_app_url}"
}

cleanup() {
  restore_env

  if [[ -n "${server_pid:-}" ]] && kill -0 "${server_pid}" >/dev/null 2>&1; then
    kill "${server_pid}" >/dev/null 2>&1 || true
    wait "${server_pid}" >/dev/null 2>&1 || true
  fi

  if [[ "${KEEP_SOURCE_DATABASE}" -ne 1 ]]; then
    dropdb --if-exists --host "${DB_HOST}" --port "${DB_PORT}" --username "${DB_USER}" "${SOURCE_DB_NAME}" >/dev/null 2>&1 || true
  fi

  if [[ "${KEEP_SERVER_LOGS}" -ne 1 ]]; then
    rm -f "${server_stdout}" "${server_stderr}"
  fi
}

trap cleanup EXIT

export DB_CONNECTION=pgsql
export DB_DATABASE="${SOURCE_DB_NAME}"
export DB_USERNAME="${DB_USER}"
export DB_PASSWORD="${DB_PASSWORD}"
export DB_HOST="${DB_HOST}"
export DB_PORT="${DB_PORT}"
export APP_URL="${base_url}"

php artisan config:clear >/dev/null
php artisan migrate --force >/dev/null
php artisan db:seed --class=DatabaseSeeder >/dev/null
php artisan db:seed --class=DemoCompanyWorkflowSeeder >/dev/null

token_json="$(php artisan ops:load-test:token --company="${COMPANY}" --name="${TOKEN_NAME}" --json)"
api_token="$(php -r '$payload=json_decode(stream_get_contents(STDIN), true); if (!is_array($payload) || empty($payload["token"])) { fwrite(STDERR, "Missing token.\n"); exit(1);} echo $payload["token"];' <<<"${token_json}")"

(
  cd "${server_working_directory}"
  php -S "${HOST}:${PORT}" "${server_script}"
) >"${server_stdout}" 2>"${server_stderr}" &
server_pid=$!

server_ready=0
for _ in $(seq 1 60); do
  if ! kill -0 "${server_pid}" >/dev/null 2>&1; then
    break
  fi

  if curl --silent --show-error --fail --max-time 5 "${base_url}/api/v1/health" >/dev/null 2>&1; then
    server_ready=1
    break
  fi

  sleep 1
done

if [[ "${server_ready}" -ne 1 ]]; then
  echo "Seeded Laravel server did not become ready." >&2
  echo "Stdout:" >&2
  cat "${server_stdout}" >&2 || true
  echo "Stderr:" >&2
  cat "${server_stderr}" >&2 || true
  exit 1
fi

load_args=(
  --base-url "${base_url}"
  --api-token "${api_token}"
  --vus "${VUS}"
  --duration "${DURATION}"
)

if [[ -n "${K6_BIN}" ]]; then
  load_args+=(--k6-bin "${K6_BIN}")
fi

load_args+=(--validation-profile "${VALIDATION_PROFILE}")

"${SCRIPT_DIR}/run-api-load-test.sh" "${load_args[@]}"

new_summary="$(find "${load_test_root}" -maxdepth 1 -type f -name '*.json' | sort | while read -r file; do
  match=0
  for existing in "${existing_summaries[@]}"; do
    if [[ "${existing}" == "${file}" ]]; then
      match=1
      break
    fi
  done
  if [[ "${match}" -eq 0 ]]; then
    printf '%s\n' "${file}"
  fi
done | tail -n 1 || true)"

new_signoff="$(find "${load_signoff_root}" -maxdepth 1 -type f -name '*.json' | sort | while read -r file; do
  match=0
  for existing in "${existing_signoffs[@]}"; do
    if [[ "${existing}" == "${file}" ]]; then
      match=1
      break
    fi
  done
  if [[ "${match}" -eq 0 ]]; then
    printf '%s\n' "${file}"
  fi
done | tail -n 1 || true)"

echo "Seeded load sign-off completed successfully."
echo "Source database: ${SOURCE_DB_NAME}"
echo "Base URL: ${base_url}"
if [[ -n "${new_summary}" ]]; then
  echo "Load summary: ${new_summary}"
fi
if [[ -n "${new_signoff}" ]]; then
  echo "Load sign-off artifact: ${new_signoff}"
fi
