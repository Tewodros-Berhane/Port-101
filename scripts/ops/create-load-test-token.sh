#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

EMAIL=""
COMPANY=""
TOKEN_NAME="ops-load-test"
ABILITIES="*"
JSON_ONLY=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --email)
      EMAIL="$2"
      shift 2
      ;;
    --company)
      COMPANY="$2"
      shift 2
      ;;
    --name)
      TOKEN_NAME="$2"
      shift 2
      ;;
    --abilities)
      ABILITIES="$2"
      shift 2
      ;;
    --json)
      JSON_ONLY=1
      shift
      ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 1
      ;;
  esac
done

cd "${REPO_ROOT}"

ARGS=("artisan" "ops:load-test:token" "--name=${TOKEN_NAME}" "--abilities=${ABILITIES}")

if [[ -n "${COMPANY}" ]]; then
  ARGS+=("--company=${COMPANY}")
fi

if [[ -n "${EMAIL}" ]]; then
  ARGS+=("${EMAIL}")
fi

if [[ "${JSON_ONLY}" -eq 1 ]]; then
  ARGS+=("--json")
fi

php "${ARGS[@]}"
