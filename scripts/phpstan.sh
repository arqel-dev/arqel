#!/usr/bin/env bash
# Wrapper do phpstan que tolera estado inicial (sem ficheiros PHP ainda).
# Usado pelo composer script `analyse`.
set -euo pipefail

cd "$(dirname "$0")/.."

# Só corre phpstan se houver pelo menos um ficheiro .php em packages/*/src.
# Directórios vazios (só com .gitkeep) não contam.
if ! find packages -mindepth 3 -path '*/src/*' -name '*.php' -not -path '*/vendor/*' 2>/dev/null | grep -q .; then
    echo "No PHP source files in packages/*/src yet — skipping phpstan analyse."
    exit 0
fi

exec vendor/bin/phpstan analyse --memory-limit=2G "$@"
