#!/usr/bin/env bash
# Add Packagist auto-update webhook to each arqel-dev/<pkg> sub-repo.
#
# Usage (from the shell where PACKAGIST_API_TOKEN + PACKAGIST_USERNAME are exported):
#   ./scripts/setup-packagist-webhooks.sh
#
# Idempotent: skips repos that already have a packagist webhook.
# Requires: gh CLI authenticated with admin:repo_hook on arqel-dev/*.

set -euo pipefail

: "${PACKAGIST_API_TOKEN:?PACKAGIST_API_TOKEN env var is required}"
: "${PACKAGIST_USERNAME:?PACKAGIST_USERNAME env var is required}"

PACKAGES=(
  actions ai audit auth cli core export
  fields fields-advanced form marketplace mcp
  nav realtime table tenant versioning widgets workflow
)

WEBHOOK_URL="https://packagist.org/api/github?username=${PACKAGIST_USERNAME}"

setup_one() {
  local pkg="$1"
  local repo="arqel-dev/$pkg"

  # Skip if a packagist webhook already exists
  local existing
  existing=$(gh api "/repos/$repo/hooks" --jq '.[] | select(.config.url | test("packagist.org/api/github")) | .id' 2>/dev/null | head -1 || true)
  if [ -n "$existing" ]; then
    echo "  [exists]  $repo (hook id $existing) — skipping"
    return 0
  fi

  echo "  [setup]   $repo"
  local resp
  resp=$(gh api -X POST "/repos/$repo/hooks" \
    -f name=web \
    -F active=true \
    -f 'events[]=push' \
    -f 'events[]=release' \
    -f config[url]="$WEBHOOK_URL" \
    -f config[content_type]=json \
    -f config[secret]="$PACKAGIST_API_TOKEN" \
    --jq '.id' 2>&1) || {
      echo "    FAILED: $resp"
      return 1
    }
  echo "    OK (hook id $resp)"
}

echo "Setting up Packagist webhooks for 19 sub-repos..."
fail=0
for pkg in "${PACKAGES[@]}"; do
  setup_one "$pkg" || fail=$((fail + 1))
  sleep 1
done

echo ""
if [ "$fail" -gt 0 ]; then
  echo "Done with $fail failure(s)."
  exit 1
fi
echo "Done. All sub-repos now auto-update Packagist on push/release."
echo ""
echo "Next: trigger a one-shot crawl on Packagist for each package to fetch v0.8.0 immediately:"
echo "  ./scripts/trigger-packagist-update.sh"
