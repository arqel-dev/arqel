#!/usr/bin/env bash
# Wrapper do phpstan usado pelo composer script `analyse`.
set -euo pipefail

cd "$(dirname "$0")/.."

exec vendor/bin/phpstan analyse --memory-limit=2G "$@"
