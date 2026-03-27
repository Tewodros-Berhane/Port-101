#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

JSON_ONLY=0
WRITE=0
EVIDENCE_FILE=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --json)
      JSON_ONLY=1
      shift
      ;;
    --write)
      WRITE=1
      shift
      ;;
    *)
      if [[ -z "${EVIDENCE_FILE}" ]]; then
        EVIDENCE_FILE="$1"
        shift
      else
        echo "Unknown argument: $1" >&2
        exit 1
      fi
      ;;
  esac
done

if [[ -z "${EVIDENCE_FILE}" ]]; then
  echo "Usage: ./scripts/ops/record-backup-signoff.sh <evidence-file> [--write] [--json]" >&2
  exit 1
fi

cd "${REPO_ROOT}"

args=(artisan ops:backup:signoff "${EVIDENCE_FILE}")

if [[ "${WRITE}" -eq 1 ]]; then
  args+=(--write)
fi

if [[ "${JSON_ONLY}" -eq 1 ]]; then
  args+=(--json)
fi

php "${args[@]}"
