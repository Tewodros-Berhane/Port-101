#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

BASE_URL_OVERRIDE="${BASE_URL:-}"
API_TOKEN_OVERRIDE="${API_TOKEN:-}"
VUS="${K6_VUS:-10}"
DURATION="${K6_DURATION:-60s}"
SUMMARY_FILE=""
SKIP_VALIDATION=0

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
    --base-url)
      BASE_URL_OVERRIDE="$2"
      shift 2
      ;;
    --api-token)
      API_TOKEN_OVERRIDE="$2"
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
    --summary-file)
      SUMMARY_FILE="$2"
      shift 2
      ;;
    --skip-validation)
      SKIP_VALIDATION=1
      shift
      ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 1
      ;;
  esac
done

if ! command -v k6 >/dev/null 2>&1; then
  echo "k6 is required on PATH to run the API load harness." >&2
  exit 1
fi

BASE_URL_OVERRIDE="${BASE_URL_OVERRIDE:-$(dotenv_value APP_URL)}"

OUTPUT_DIR="${REPO_ROOT}/storage/app/load-tests"
mkdir -p "${OUTPUT_DIR}"

if [[ -z "${SUMMARY_FILE}" ]]; then
  SUMMARY_FILE="${OUTPUT_DIR}/api-smoke-$(date -u +%Y%m%d-%H%M%S).json"
fi

cd "${REPO_ROOT}"

export BASE_URL="${BASE_URL_OVERRIDE}"
export API_TOKEN="${API_TOKEN_OVERRIDE}"
export K6_VUS="${VUS}"
export K6_DURATION="${DURATION}"
export K6_WEB_DASHBOARD="false"

k6 run \
  --summary-export "${SUMMARY_FILE}" \
  "${SCRIPT_DIR}/k6-api-smoke.js"

echo "k6 summary written to ${SUMMARY_FILE}"

if [[ "${SKIP_VALIDATION}" -eq 0 ]]; then
  php artisan ops:performance:validate-load "${SUMMARY_FILE}" --write
fi
