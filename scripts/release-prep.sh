#!/usr/bin/env bash
# release-prep.sh — Preflight checks before tagging an Arqel release.
#
# Usage:
#   ./scripts/release-prep.sh
#
# Runs install + tests + lint across PHP and JS packages and prints a final
# summary. Exits non-zero on the first hard failure (missing tooling) or with
# an aggregated non-zero status if any package check failed.

set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# ---- ANSI helpers ----------------------------------------------------------

if [[ -t 1 ]]; then
    C_RESET=$'\033[0m'
    C_BOLD=$'\033[1m'
    C_GREEN=$'\033[32m'
    C_RED=$'\033[31m'
    C_YELLOW=$'\033[33m'
    C_CYAN=$'\033[36m'
else
    C_RESET=''; C_BOLD=''; C_GREEN=''; C_RED=''; C_YELLOW=''; C_CYAN=''
fi

log()    { printf '%s[release-prep]%s %s\n' "$C_CYAN" "$C_RESET" "$*"; }
info()   { printf '  %s\n' "$*"; }
ok()     { printf '  %s✓%s %s\n' "$C_GREEN" "$C_RESET" "$*"; }
warn()   { printf '  %s!%s %s\n' "$C_YELLOW" "$C_RESET" "$*"; }
fail()   { printf '  %s✗%s %s\n' "$C_RED" "$C_RESET" "$*"; }
section(){ printf '\n%s== %s ==%s\n' "$C_BOLD" "$*" "$C_RESET"; }

# ---- Result tracking -------------------------------------------------------

declare -a OK_STEPS=()
declare -a FAILED_STEPS=()
declare -a SKIPPED_STEPS=()

record_ok()      { OK_STEPS+=("$1"); ok "$1"; }
record_fail()    { FAILED_STEPS+=("$1"); fail "$1"; }
record_skip()    { SKIPPED_STEPS+=("$1"); warn "$1 (skipped)"; }

run_step() {
    local label="$1"; shift
    info "→ $label"
    if "$@"; then
        record_ok "$label"
        return 0
    else
        record_fail "$label"
        return 1
    fi
}

# ---- Tool checks -----------------------------------------------------------

require_tool() {
    local tool="$1"
    if ! command -v "$tool" >/dev/null 2>&1; then
        printf '%s[release-prep]%s missing required tool: %s\n' "$C_RED" "$C_RESET" "$tool" >&2
        exit 2
    fi
}

section "Tool availability"
require_tool php
require_tool composer
require_tool pnpm
require_tool node
ok "php $(php -r 'echo PHP_VERSION;')"
ok "composer $(composer --version | awk '{print $3}')"
ok "node $(node --version)"
ok "pnpm $(pnpm --version)"

# ---- 1. Composer install in PHP packages ----------------------------------

section "1. Composer install (packages/*)"
for dir in packages/*/; do
    pkg="${dir%/}"
    name="$(basename "$pkg")"
    if [[ -f "$pkg/composer.json" ]]; then
        run_step "composer install [$name]" composer --working-dir="$pkg" install --prefer-dist --no-interaction --no-progress --quiet \
            || true
    else
        record_skip "composer install [$name] — no composer.json"
    fi
done

# ---- 2. pnpm install at root ----------------------------------------------

section "2. pnpm install (root)"
run_step "pnpm install" pnpm install --frozen-lockfile=false --reporter=silent || true

# ---- 3. Arqel doctor / audit (best-effort) --------------------------------

section "3. Arqel doctor / audit"
# These commands only exist inside a Laravel host app, not in this monorepo
# directly. We document the manual flow and skip non-fatally when artisan is
# absent.
if [[ -f artisan ]]; then
    run_step "php artisan arqel:doctor --strict" php artisan arqel:doctor --strict || true
    run_step "php artisan arqel:audit --strict"  php artisan arqel:audit --strict  || true
else
    warn "Skipping arqel:doctor / arqel:audit — no artisan in monorepo root."
    info "  To run them, scaffold a fresh Laravel app, require arqel/* via path"
    info "  repositories, then execute:"
    info "      php artisan arqel:doctor --strict"
    info "      php artisan arqel:audit  --strict"
    SKIPPED_STEPS+=("arqel:doctor (no artisan)")
    SKIPPED_STEPS+=("arqel:audit (no artisan)")
fi

# ---- 4. Pest tests for each PHP package ------------------------------------

section "4. Pest (packages/*)"
for dir in packages/*/; do
    pkg="${dir%/}"
    name="$(basename "$pkg")"
    if [[ -x "$pkg/vendor/bin/pest" ]]; then
        run_step "pest [$name]" "$pkg/vendor/bin/pest" --colors=never --bail \
            -d memory_limit=512M \
            --configuration="$pkg/phpunit.xml" || true
    elif [[ -f "$pkg/phpunit.xml" || -f "$pkg/phpunit.xml.dist" ]]; then
        record_skip "pest [$name] — vendor/bin/pest missing (composer install failed?)"
    else
        record_skip "pest [$name] — no phpunit config"
    fi
done

# ---- 5. Vitest for each JS package -----------------------------------------

section "5. Vitest (packages-js/*)"
for dir in packages-js/*/; do
    pkg="${dir%/}"
    name="$(basename "$pkg")"
    if [[ -f "$pkg/package.json" ]]; then
        if grep -q '"test"' "$pkg/package.json"; then
            run_step "vitest [$name]" pnpm --filter "./$pkg" run test --silent || true
        else
            record_skip "vitest [$name] — no test script"
        fi
    fi
done

# ---- Final summary ---------------------------------------------------------

section "Summary"
printf '%s%d%s passed   ' "$C_GREEN" "${#OK_STEPS[@]}"     "$C_RESET"
printf '%s%d%s failed   ' "$C_RED"   "${#FAILED_STEPS[@]}" "$C_RESET"
printf '%s%d%s skipped\n' "$C_YELLOW" "${#SKIPPED_STEPS[@]}" "$C_RESET"

if (( ${#FAILED_STEPS[@]} > 0 )); then
    echo
    printf '%sFailed steps:%s\n' "$C_RED" "$C_RESET"
    for s in "${FAILED_STEPS[@]}"; do
        printf '  - %s\n' "$s"
    done
    echo
    printf '%sRelease NOT ready.%s Investigate the failures above.\n' "$C_RED" "$C_RESET"
    exit 1
fi

if (( ${#SKIPPED_STEPS[@]} > 0 )); then
    echo
    printf '%sSkipped (informational):%s\n' "$C_YELLOW" "$C_RESET"
    for s in "${SKIPPED_STEPS[@]}"; do
        printf '  - %s\n' "$s"
    done
fi

echo
printf '%sAll executed steps passed.%s Ready for tag (after manual verification of skipped items).\n' "$C_GREEN" "$C_RESET"
exit 0
