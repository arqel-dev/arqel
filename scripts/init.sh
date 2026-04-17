#!/usr/bin/env bash
# Arqel — Setup inicial do ambiente de desenvolvimento
# Uso: ./scripts/init.sh

set -euo pipefail

# Cores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

log() { echo -e "${BLUE}[init]${NC} $*"; }
success() { echo -e "${GREEN}✓${NC} $*"; }
warn() { echo -e "${YELLOW}⚠${NC} $*"; }
error() { echo -e "${RED}✗${NC} $*"; exit 1; }

log "Iniciando setup do Arqel..."

# ===== Verificação de versões =====

log "Verificando PHP..."
if ! command -v php &> /dev/null; then
    error "PHP não encontrado. Instalar PHP 8.3+"
fi

PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
PHP_MAJOR=$(echo "$PHP_VERSION" | cut -d. -f1)
PHP_MINOR=$(echo "$PHP_VERSION" | cut -d. -f2)
if [[ "$PHP_MAJOR" -lt 8 ]] || [[ "$PHP_MAJOR" -eq 8 && "$PHP_MINOR" -lt 3 ]]; then
    error "PHP 8.3+ requerido. Atual: $PHP_VERSION"
fi
success "PHP $PHP_VERSION"

log "Verificando Composer..."
if ! command -v composer &> /dev/null; then
    error "Composer não encontrado. Instalar: https://getcomposer.org"
fi
success "Composer $(composer --version --no-ansi | head -1)"

log "Verificando Node.js..."

# Carrega nvm se disponível (scripts não herdam source do ~/.bashrc)
if [[ -z "${NVM_DIR:-}" ]] && [[ -d "$HOME/.nvm" ]]; then
    export NVM_DIR="$HOME/.nvm"
fi
if [[ -n "${NVM_DIR:-}" ]] && [[ -s "$NVM_DIR/nvm.sh" ]]; then
    # shellcheck source=/dev/null
    \. "$NVM_DIR/nvm.sh"
    if [[ -f ".nvmrc" ]]; then
        nvm use > /dev/null 2>&1 || nvm install > /dev/null 2>&1
    fi
fi

if ! command -v node &> /dev/null; then
    error "Node.js não encontrado. Instalar Node 20.9+ (LTS)"
fi

NODE_VERSION=$(node -v | sed 's/v//' | cut -d. -f1)
if [[ "$NODE_VERSION" -lt 20 ]]; then
    error "Node 20+ requerido. Atual: $(node -v). Se usa nvm, rode: nvm use"
fi
success "Node $(node -v)"

log "Verificando pnpm..."
if ! command -v pnpm &> /dev/null; then
    warn "pnpm não encontrado. Habilitando via corepack..."
    if ! command -v corepack &> /dev/null; then
        error "corepack não encontrado (embutido em Node 16.10+). Instale pnpm manualmente: https://pnpm.io/installation"
    fi
    corepack enable pnpm
fi
success "pnpm $(pnpm -v)"

log "Verificando git..."
if ! command -v git &> /dev/null; then
    error "git não encontrado"
fi
success "git $(git --version | awk '{print $3}')"

# ===== Instalação de dependências =====

if [[ -f "composer.json" ]]; then
    log "Instalando dependências Composer..."
    composer install --no-interaction --prefer-dist
    success "Composer deps instaladas"
fi

if [[ -f "package.json" ]]; then
    log "Instalando dependências npm (via pnpm)..."
    pnpm install
    success "pnpm deps instaladas"
fi

# ===== Git hooks =====
#
# Hooks são geridos por husky (ver .husky/). O `pnpm install` acima invocou
# o script `prepare` (package.json), que executa `husky`, o qual aponta o
# `core.hooksPath` do git para `.husky/`. Nada mais a configurar manualmente.

if [[ -d ".git" ]] && [[ -d ".husky" ]]; then
    HOOKS_PATH=$(git config core.hooksPath || echo "")
    if [[ "$HOOKS_PATH" == ".husky/_" ]] || [[ "$HOOKS_PATH" == ".husky" ]]; then
        success "Git hooks geridos via husky ($HOOKS_PATH)"
    else
        warn "Husky não está activo — corre 'pnpm install' para activar os hooks"
    fi
fi

# ===== Verificação final =====

log "Rodando verificações finais..."

if [[ -f "vendor/bin/pest" ]]; then
    if vendor/bin/pest --parallel --compact 2>/dev/null; then
        success "Testes PHP passam"
    else
        warn "Testes PHP ainda não existem ou estão falhando (esperado em setup inicial)"
    fi
fi

if [[ -f "node_modules/.bin/vitest" ]]; then
    if pnpm test --run --reporter=minimal 2>/dev/null; then
        success "Testes JS passam"
    else
        warn "Testes JS ainda não existem ou estão falhando (esperado em setup inicial)"
    fi
fi

# ===== Próximos passos =====

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  ✓ Setup completo!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo "Próximos passos:"
echo ""
echo "  1. Ler CLAUDE.md para entender convenções"
echo "  2. Ler docs/tickets/current.md para ver ticket ativo"
echo "  3. Começar implementação:"
echo "     ${BLUE}claude${NC}  # Inicia Claude Code"
echo ""
echo "Comandos úteis:"
echo "  ${BLUE}pnpm test${NC}              Rodar testes"
echo "  ${BLUE}pnpm lint${NC}              Lint"
echo "  ${BLUE}./scripts/next-ticket.sh${NC}  Ver próximo ticket"
echo ""
