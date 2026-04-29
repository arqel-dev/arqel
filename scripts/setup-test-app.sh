#!/usr/bin/env bash
#
# scripts/setup-test-app.sh
#
# Wires a sibling Laravel app to the Arqel monorepo via path repos
# and pnpm link, so we can dogfood the framework end-to-end without
# publishing anything.
#
# Defaults to ../arqel-test (sibling of this monorepo). Override with
# the first positional argument:
#   ./scripts/setup-test-app.sh /path/to/another-app
#
# What it does, in order:
#   1. Resolves the target app path and verifies it's a Laravel app
#   2. Adds the arqel/* path repository to the app's composer.json
#      (idempotent — won't duplicate if already present)
#   3. Runs `composer require` for all 8 Arqel PHP packages
#   4. Builds every packages-js/* tsup output
#   5. pnpm link --global each @arqel/* package, then links them in
#      the test app
#   6. Adds React + Inertia peer dev deps
#   7. Rewrites resources/js/app.tsx and resources/css/app.css with
#      the createArqelApp boilerplate (preserves any custom code via
#      a .bak backup)
#   8. Updates vite.config to point at app.tsx instead of app.js
#   9. Patches App\Providers\ArqelServiceProvider with the Arqel::panel(...)
#      call (only when the file still has the broken stub)
#  10. Registers ArqelServiceProvider in bootstrap/providers.php
#  11. Runs `php artisan migrate` and prints how to start dev servers
#
# Re-running is safe; each step is idempotent.

set -euo pipefail

# ---------- helpers ------------------------------------------------

ARQEL_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_APP="${1:-${ARQEL_ROOT%/*}/arqel-test}"

color_reset='\033[0m'
color_blue='\033[1;34m'
color_green='\033[1;32m'
color_yellow='\033[1;33m'
color_red='\033[1;31m'

log()  { printf "${color_blue}==> %s${color_reset}\n" "$*"; }
ok()   { printf "${color_green}    %s${color_reset}\n" "$*"; }
warn() { printf "${color_yellow}    %s${color_reset}\n" "$*"; }
die()  { printf "${color_red}!! %s${color_reset}\n" "$*" >&2; exit 1; }

require_file() {
  [ -f "$1" ] || die "Required file missing: $1"
}

# Detect Node toolchain. nvm + corepack expose pnpm only when shimmed.
detect_pnpm() {
  if command -v pnpm >/dev/null 2>&1; then
    PNPM_BIN="pnpm"
    return
  fi

  for candidate in \
    "$HOME/.nvm/versions/node/v22.22.0/bin/pnpm" \
    "$HOME/.nvm/versions/node/v20"*"/bin/pnpm"; do
    if [ -x "$candidate" ]; then
      PNPM_BIN="$candidate"
      return
    fi
  done

  die "pnpm not found in PATH or under ~/.nvm. Run 'corepack enable' or install pnpm."
}

# ---------- 1. resolve target -------------------------------------

log "Resolving target Laravel app"
[ -d "$TARGET_APP" ] || die "Target directory not found: $TARGET_APP"
[ -f "$TARGET_APP/artisan" ] || die "Not a Laravel app (no artisan): $TARGET_APP"
require_file "$TARGET_APP/composer.json"
require_file "$TARGET_APP/package.json"
ok "Target app: $TARGET_APP"

detect_pnpm
ok "pnpm: $PNPM_BIN"

# ---------- 2. composer path repo ---------------------------------

log "Ensuring arqel/* path repository is wired in composer.json"

cd "$TARGET_APP"

# Read repositories key. We look for any entry whose url contains
# "arqel/packages/*"; if none, we add ours.
if grep -q '"arqel"' composer.json && grep -q "$ARQEL_ROOT/packages/\*" composer.json; then
  ok "Path repo already present"
else
  warn "Path repo missing or pointing elsewhere — patching composer.json"
  # Use composer config to add the repository idempotently.
  composer config repositories.arqel \
    "{\"type\":\"path\",\"url\":\"$ARQEL_ROOT/packages/*\"}" \
    >/dev/null
  ok "composer.json patched"
fi

# ---------- 3. composer require Arqel PHP packages ----------------

log "Installing Arqel PHP packages (composer require)"

ARQEL_PHP_PACKAGES=(
  "arqel/core:dev-main"
  "arqel/fields:dev-main"
  "arqel/table:dev-main"
  "arqel/form:dev-main"
  "arqel/actions:dev-main"
  "arqel/auth:dev-main"
  "arqel/nav:dev-main"
  "arqel/tenant:dev-main"
)

# Determine which packages still need installing — composer is slow, so
# skip the require call when everything is already there.
MISSING_PHP=()
for spec in "${ARQEL_PHP_PACKAGES[@]}"; do
  pkg="${spec%%:*}"
  if ! grep -q "\"$pkg\"" composer.json; then
    MISSING_PHP+=("$spec")
  fi
done

if [ ${#MISSING_PHP[@]} -eq 0 ]; then
  ok "All 8 Arqel PHP packages already required"
else
  warn "Adding ${#MISSING_PHP[@]} missing package(s)…"
  composer require "${MISSING_PHP[@]}" --no-interaction
  ok "composer require done"
fi

# ---------- 4. build packages-js -----------------------------------

log "Building Arqel JS packages (tsup)"
(
  cd "$ARQEL_ROOT"
  "$PNPM_BIN" -r --filter "@arqel/*" build >/dev/null
)
ok "All @arqel/* packages built"

# ---------- 5. file: deps via pnpm link ----------------------------
#
# `pnpm link --global` would work but requires a one-off `pnpm
# setup` that creates ~/.local/share/pnpm/global and edits the
# user's shell init. We avoid that by passing the absolute path
# directly to `pnpm link` — it writes a `link:<absolute-path>`
# entry into package.json (a path-style dep) without touching any
# global registry.

log "Linking @arqel/* into the test app via file: paths"

cd "$TARGET_APP"

ARQEL_LINK_PATHS=(
  "$ARQEL_ROOT/packages-js/types"
  "$ARQEL_ROOT/packages-js/react"
  "$ARQEL_ROOT/packages-js/hooks"
  "$ARQEL_ROOT/packages-js/ui"
  "$ARQEL_ROOT/packages-js/fields-js"
)

# Re-link unconditionally — pnpm is fast on a no-op and this avoids
# stale symlinks after a packages-js rebuild.
for abs in "${ARQEL_LINK_PATHS[@]}"; do
  "$PNPM_BIN" link "$abs" >/dev/null
done
ok "5 @arqel/* deps linked into $TARGET_APP/node_modules"

# ---------- 6. peer dev deps ---------------------------------------

log "Ensuring React + Inertia peer dev deps are installed"

NEEDED_DEPS=(
  "@inertiajs/react"
  "react"
  "react-dom"
  "@types/react"
  "@types/react-dom"
)

MISSING_NPM=()
for pkg in "${NEEDED_DEPS[@]}"; do
  if ! grep -q "\"$pkg\"" package.json; then
    MISSING_NPM+=("$pkg")
  fi
done

if [ ${#MISSING_NPM[@]} -eq 0 ]; then
  ok "All peer deps already declared"
else
  warn "Installing ${#MISSING_NPM[@]} peer dep(s)…"
  "$PNPM_BIN" add -D "${MISSING_NPM[@]}" >/dev/null
  ok "peer deps installed"
fi

# ---------- 7. resources/js/app.tsx + resources/css/app.css -------

log "Configuring resources/js/app.tsx"

APP_TSX="$TARGET_APP/resources/js/app.tsx"
APP_TSX_MARKER="@arqel/ui/styles.css"

if [ -f "$APP_TSX" ] && grep -q "$APP_TSX_MARKER" "$APP_TSX"; then
  ok "app.tsx already wired"
else
  if [ -f "$APP_TSX" ]; then
    cp "$APP_TSX" "$APP_TSX.bak"
    warn "Existing app.tsx backed up to app.tsx.bak"
  fi
  cat > "$APP_TSX" <<'TSX'
import '@arqel/ui/styles.css';
import '@arqel/fields/register';
import { createArqelApp } from '@arqel/react/inertia';
import { arqelPages } from '@arqel/ui/pages';

createArqelApp({
  appName: import.meta.env.VITE_APP_NAME ?? 'Arqel Test',
  // Spread arqelPages first so user pages can override per-resource
  // (e.g. resources/js/Pages/Arqel/Posts/Index.tsx).
  pages: { ...arqelPages, ...import.meta.glob('./Pages/**/*.tsx') },
});
TSX
  ok "app.tsx written"
fi

log "Configuring resources/css/app.css"

APP_CSS="$TARGET_APP/resources/css/app.css"
ARQEL_CSS_IMPORT="@import '@arqel/ui/styles.css';"

if grep -q "@arqel/ui/styles.css" "$APP_CSS"; then
  ok "app.css already imports @arqel/ui"
else
  if grep -q "@import 'tailwindcss'" "$APP_CSS"; then
    # Append after the tailwind import (preserve user's @theme block etc).
    awk -v ins="$ARQEL_CSS_IMPORT" '
      { print }
      /^@import .tailwindcss.;/ && !done { print ins; done=1 }
    ' "$APP_CSS" > "$APP_CSS.tmp" && mv "$APP_CSS.tmp" "$APP_CSS"
    ok "app.css patched (Arqel import added after tailwindcss)"
  else
    {
      printf "@import 'tailwindcss';\n"
      printf "%s\n" "$ARQEL_CSS_IMPORT"
      cat "$APP_CSS"
    } > "$APP_CSS.tmp" && mv "$APP_CSS.tmp" "$APP_CSS"
    ok "app.css patched (added both imports at the top)"
  fi
fi

# ---------- 8. vite.config: app.js → app.tsx ----------------------

log "Updating Vite config entry point to app.tsx"

VITE_CONFIG=""
for candidate in vite.config.ts vite.config.js vite.config.mjs; do
  if [ -f "$TARGET_APP/$candidate" ]; then
    VITE_CONFIG="$TARGET_APP/$candidate"
    break
  fi
done

if [ -z "$VITE_CONFIG" ]; then
  warn "No vite.config found — skipping. Wire 'resources/js/app.tsx' manually."
else
  if grep -q "resources/js/app.tsx" "$VITE_CONFIG"; then
    ok "vite already references app.tsx"
  else
    sed -i.bak "s|resources/js/app\\.js|resources/js/app.tsx|g" "$VITE_CONFIG"
    if grep -q "resources/js/app.tsx" "$VITE_CONFIG"; then
      ok "vite config updated (.bak left as backup)"
    else
      warn "Couldn't auto-patch $VITE_CONFIG — please change app.js → app.tsx manually."
    fi
  fi

  # Composer path repos copy each package's vendor/ alongside the
  # symlink (~65k+ files), and Vite tries to watch all of it,
  # blowing past the inotify limit (ENOSPC). Add a server.watch
  # ignored block when missing.
  if ! grep -q "vendor/arqel" "$VITE_CONFIG"; then
    if grep -q "watch:" "$VITE_CONFIG"; then
      warn "Vite already has a server.watch block — please add vendor/arqel/* + node_modules manually."
    else
      cp "$VITE_CONFIG" "$VITE_CONFIG.watch.bak"
      python3 - "$VITE_CONFIG" <<'PY' || warn "Couldn't auto-inject server.watch.ignored — add manually."
import re, sys, pathlib
path = pathlib.Path(sys.argv[1])
src = path.read_text()
ignored = """    server: {
        watch: {
            ignored: [
                '**/vendor/arqel/*/vendor/**',
                '**/vendor/arqel/*/node_modules/**',
                '**/storage/framework/views/**',
            ],
        },
    },
"""
# Replace existing `server: { watch: { ignored: [...] } }` block
# only when it doesn't already mention vendor/arqel.
if "vendor/arqel" in src:
    sys.exit(0)

new = re.sub(
    r"server:\s*\{\s*watch:\s*\{[^}]*\},\s*\},",
    ignored.strip(),
    src,
    count=1,
    flags=re.DOTALL,
)
if new == src:
    # No existing block — insert before the closing `});` of
    # defineConfig({ ... });
    new = re.sub(
        r"(\n\s*\}\);\s*$)",
        f"\n{ignored}\\1",
        src,
        count=1,
    )
path.write_text(new)
PY
      if grep -q "vendor/arqel" "$VITE_CONFIG"; then
        ok "vite config gained server.watch.ignored (.watch.bak left as backup)"
      else
        warn "Auto-inject of server.watch.ignored failed — add the block manually:"
        warn "  server: { watch: { ignored: ['**/vendor/arqel/*/vendor/**', '**/vendor/arqel/*/node_modules/**'] } }"
      fi
    fi
  else
    ok "vite already filters vendor/arqel from watch"
  fi
fi

# ---------- 9. App\Providers\ArqelServiceProvider ----------------

log "Patching app/Providers/ArqelServiceProvider.php"

PROVIDER_PHP="$TARGET_APP/app/Providers/ArqelServiceProvider.php"

if [ ! -f "$PROVIDER_PHP" ]; then
  warn "ArqelServiceProvider not found — run 'php artisan arqel:install --no-frontend' first."
elif grep -q "Arqel::panel(" "$PROVIDER_PHP"; then
  ok "Provider already calls Arqel::panel(...)"
else
  cp "$PROVIDER_PHP" "$PROVIDER_PHP.bak"
  cat > "$PROVIDER_PHP" <<'PHP'
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Arqel\Resources\UserResource;
use Arqel\Core\Facades\Arqel;
use Illuminate\Support\ServiceProvider;

final class ArqelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Arqel::panel('admin')
            ->path('admin')
            ->brand('Arqel Test')
            ->resources([
                // UserResource::class — uncomment after running:
                // php artisan arqel:resource User --with-policy
            ])
            ->middleware(['web']);
    }
}
PHP
  ok "Provider rewritten (.bak left as backup)"
fi

# ---------- 10. bootstrap/providers.php ---------------------------

log "Ensuring ArqelServiceProvider is registered in bootstrap/providers.php"

BOOTSTRAP_PROVIDERS="$TARGET_APP/bootstrap/providers.php"

if [ ! -f "$BOOTSTRAP_PROVIDERS" ]; then
  warn "bootstrap/providers.php missing — non-Laravel-12+ app? skipping."
elif grep -q "ArqelServiceProvider" "$BOOTSTRAP_PROVIDERS"; then
  ok "ArqelServiceProvider already registered"
else
  cp "$BOOTSTRAP_PROVIDERS" "$BOOTSTRAP_PROVIDERS.bak"
  awk '
    /^use App\\Providers\\AppServiceProvider;/ {
      print
      print "use App\\Providers\\ArqelServiceProvider;"
      next
    }
    /AppServiceProvider::class,/ && !injected {
      print
      print "    ArqelServiceProvider::class,"
      injected = 1
      next
    }
    { print }
  ' "$BOOTSTRAP_PROVIDERS" > "$BOOTSTRAP_PROVIDERS.tmp" \
    && mv "$BOOTSTRAP_PROVIDERS.tmp" "$BOOTSTRAP_PROVIDERS"
  ok "bootstrap/providers.php patched (.bak left as backup)"
fi

# ---------- 11. migrate + final hint ------------------------------

log "Running migrations"
php artisan migrate --force --no-interaction || warn "migrate failed — re-run manually"

cat <<EOF

${color_green}✔ Setup complete${color_reset}

Next steps in $TARGET_APP:

  ${color_yellow}# 1. Generate your first Resource:${color_reset}
  php artisan arqel:resource User --with-policy

  ${color_yellow}# 2. Uncomment UserResource::class in app/Providers/ArqelServiceProvider.php${color_reset}

  ${color_yellow}# 3. Start the dev servers (in two terminals):${color_reset}
  php artisan serve            # http://127.0.0.1:8000
  pnpm dev                     # Vite

  ${color_yellow}# 4. (Optional) Make a user via tinker:${color_reset}
  php artisan tinker
  > User::factory()->create(['email' => 'a@b.c'])

  ${color_yellow}# 5. Visit http://127.0.0.1:8000/admin/users${color_reset}

To re-sync after pulling new Arqel changes, just re-run:
  $ARQEL_ROOT/scripts/setup-test-app.sh

EOF
