#!/usr/bin/env bash
# Trigger a one-shot Packagist update for each arqel-dev/<pkg> package.
# Use this after first submission to make Packagist fetch v0.8.0 immediately
# instead of waiting for the next scheduled crawl.
#
# Usage (from the shell where PACKAGIST_API_TOKEN + PACKAGIST_USERNAME are exported):
#   ./scripts/trigger-packagist-update.sh

set -euo pipefail

: "${PACKAGIST_API_TOKEN:?PACKAGIST_API_TOKEN env var is required}"
PACKAGIST_USERNAME="${PACKAGIST_USERNAME:-diogocoutinho}"

PACKAGES=(
  actions ai audit auth cli core export
  fields fields-advanced form marketplace mcp
  nav realtime table tenant versioning widgets workflow
  framework
)

trigger_one() {
  local pkg="$1"
  echo "  [update]  arqel-dev/$pkg"
  local resp
  resp=$(curl -fsS -X POST \
    "https://packagist.org/api/update-package?username=${PACKAGIST_USERNAME}&apiToken=${PACKAGIST_API_TOKEN}" \
    -H 'Content-Type: application/json' \
    -d "{\"repository\":{\"url\":\"https://packagist.org/packages/arqel-dev/${pkg}\"}}" \
    -w '\nHTTP_STATUS:%{http_code}' || echo "ERROR")
  local status
  status=$(echo "$resp" | grep -oE 'HTTP_STATUS:[0-9]+' | cut -d: -f2)
  if [ "$status" = "202" ]; then
    echo "    OK ($status)"
  else
    echo "    FAILED — response:"
    echo "$resp" | sed 's/^/      /'
    return 1
  fi
}

echo "Triggering update for ${#PACKAGES[@]} packages..."
fail=0
for pkg in "${PACKAGES[@]}"; do
  trigger_one "$pkg" || fail=$((fail + 1))
  sleep 1
done

echo ""
[ "$fail" -gt 0 ] && { echo "Done with $fail failure(s)."; exit 1; }
echo "Done. Wait ~30s and check: curl -s https://packagist.org/packages/arqel-dev/core.json | jq '.package.versions | keys'"
