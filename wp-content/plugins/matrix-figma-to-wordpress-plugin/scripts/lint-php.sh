#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FAILED=0

while IFS= read -r -d '' file; do
  if ! php -l "$file" >/dev/null; then
    php -l "$file"
    FAILED=1
  fi
done < <(find "$ROOT" -name '*.php' -not -path '*/.git/*' -print0)

if [[ "$FAILED" -ne 0 ]]; then
  exit 1
fi

echo "All PHP files passed syntax check."
