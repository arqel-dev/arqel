#!/usr/bin/env bash
# Wrapper do phpstan que tolera estado inicial (sem packages PHP ainda).
# Usado pelo composer script `analyse`.
set -euo pipefail

cd "$(dirname "$0")/.."

shopt -s nullglob
src_dirs=(packages/*/src)

if [ ${#src_dirs[@]} -eq 0 ]; then
    echo "No PHP packages with src/ yet — skipping phpstan analyse."
    exit 0
fi

exec vendor/bin/phpstan analyse --memory-limit=1G "$@"
