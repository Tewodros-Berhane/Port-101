#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

WORKSPACE=""
JSON_ONLY=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --workspace)
      WORKSPACE="$2"
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

ARGS=("artisan" "ops:recovery:signoff" "--write")

if [[ -n "${WORKSPACE}" ]]; then
  ARGS+=("--workspace=${WORKSPACE}")
fi

if [[ "${JSON_ONLY}" -eq 1 ]]; then
  ARGS+=("--json")
fi

php "${ARGS[@]}"
