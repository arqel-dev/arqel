#!/usr/bin/env bash
# Arqel — Pre-commit check (chamado por Claude Code hook)
# Roda lint + typecheck + testes rápidos antes de permitir commit

set -euo pipefail

GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

ok() { echo -e "${GREEN}✓${NC} $*"; }
fail() { echo -e "${RED}✗${NC} $*"; exit 1; }

echo "Rodando pre-commit checks..."

# Só corre se existirem as ferramentas (skip em setup inicial)

if [[ -f "vendor/bin/pint" ]]; then
    vendor/bin/pint --test || fail "PHP Pint failed"
    ok "PHP Pint"
fi

if [[ -f "vendor/bin/phpstan" ]]; then
    vendor/bin/phpstan analyse --no-progress || fail "PHPStan failed"
    ok "PHPStan"
fi

if [[ -f "node_modules/.bin/eslint" ]]; then
    pnpm lint || fail "ESLint failed"
    ok "ESLint"
fi

if [[ -f "node_modules/.bin/tsc" ]]; then
    pnpm typecheck || fail "TypeScript check failed"
    ok "TypeScript"
fi

echo ""
ok "Pre-commit checks passed"
