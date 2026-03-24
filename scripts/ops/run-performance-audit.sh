#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

OUTPUT_FILE=""
JSON_ONLY=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --output-file)
      OUTPUT_FILE="$2"
      shift 2
      ;;
    --json-only)
      JSON_ONLY=1
      shift
      ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 1
      ;;
  esac
done

OUTPUT_DIR="${REPO_ROOT}/storage/app/performance-audits"
mkdir -p "${OUTPUT_DIR}"

if [[ -z "${OUTPUT_FILE}" ]]; then
  OUTPUT_FILE="${OUTPUT_DIR}/performance-audit-$(date -u +%Y%m%d-%H%M%S).json"
fi

cd "${REPO_ROOT}"

if [[ "${JSON_ONLY}" -eq 0 ]]; then
  php artisan ops:performance:audit
fi

php artisan ops:performance:audit --json > "${OUTPUT_FILE}"

echo "Performance audit JSON written to ${OUTPUT_FILE}"

if [[ "${JSON_ONLY}" -eq 1 ]]; then
  cat "${OUTPUT_FILE}"
fi
