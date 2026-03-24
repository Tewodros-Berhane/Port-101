#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

INPUT_FILE="${1:-}"
DESTINATION_ROOT="${2:-${REPO_ROOT}}"

if [[ -z "${INPUT_FILE}" ]]; then
  echo "Usage: restore-storage.sh <archive-file> [destination-root]" >&2
  exit 1
fi

if [[ ! -f "${INPUT_FILE}" ]]; then
  echo "Archive file not found: ${INPUT_FILE}" >&2
  exit 1
fi

mkdir -p "${DESTINATION_ROOT}"

tar -xzf "${INPUT_FILE}" -C "${DESTINATION_ROOT}"

echo "Storage archive restored into ${DESTINATION_ROOT}."
