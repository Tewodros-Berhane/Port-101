#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

OUTPUT_DIR="${OUTPUT_DIR:-${REPO_ROOT}/storage/app/backups/storage}"
TIMESTAMP="${TIMESTAMP:-$(date -u +%Y%m%d-%H%M%S)}"
ARCHIVE_PATH="${OUTPUT_DIR}/port-101-storage-${TIMESTAMP}.tar.gz"

mkdir -p "${OUTPUT_DIR}"

SOURCE_PATHS=(
  "storage/app/private"
  "storage/app/public"
)

EXISTING_PATHS=()
for path in "${SOURCE_PATHS[@]}"; do
  if [[ -e "${REPO_ROOT}/${path}" ]]; then
    EXISTING_PATHS+=("${path}")
  fi
done

if [[ "${#EXISTING_PATHS[@]}" -eq 0 ]]; then
  echo "No storage paths found to archive." >&2
  exit 1
fi

tar -czf "${ARCHIVE_PATH}" -C "${REPO_ROOT}" "${EXISTING_PATHS[@]}"

echo "${ARCHIVE_PATH}"
