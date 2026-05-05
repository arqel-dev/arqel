#!/usr/bin/env bash
# Submit all 19 arqel-dev PHP packages to Packagist via API.
#
# Usage:
#   export PACKAGIST_API_TOKEN='your-token'
#   export PACKAGIST_USERNAME='arqel-dev'  # optional, defaults to arqel-dev
#   ./scripts/submit-packagist.sh
#
# Idempotent: if a package is already submitted, prints "exists" and skips.

set -euo pipefail

: "${PACKAGIST_API_TOKEN:?PACKAGIST_API_TOKEN env var is required}"
PACKAGIST_USERNAME="${PACKAGIST_USERNAME:-arqel-dev}"

PACKAGES=(
  actions ai audit auth cli core export
  fields fields-advanced form marketplace mcp
  nav realtime table tenant versioning widgets workflow
)

submit_one() {
  local pkg="$1"
  local repo_url="https://github.com/arqel-dev/$pkg"

  # Fast-path: skip if already known to Packagist.
  local check
  check=$(curl -fsS -o /dev/null -w '%{http_code}' "https://packagist.org/packages/arqel-dev/$pkg.json" || echo "000")
  if [ "$check" = "200" ]; then
    echo "  [exists]  arqel-dev/$pkg — skipping"
    return 0
  fi

  echo "  [submit]  arqel-dev/$pkg"
  local resp
  resp=$(curl -fsS -X POST \
    "https://packagist.org/api/create-package?username=${PACKAGIST_USERNAME}&apiToken=${PACKAGIST_API_TOKEN}" \
    -H "Content-Type: application/json" \
    -d "{\"repository\":{\"url\":\"${repo_url}\"}}" \
    -w '\nHTTP_STATUS:%{http_code}' || echo "ERROR")

  local status
  status=$(echo "$resp" | grep -oE 'HTTP_STATUS:[0-9]+' | cut -d: -f2)
  if [ "$status" = "201" ] || [ "$status" = "202" ]; then
    echo "    OK ($status)"
  else
    echo "    FAILED — response:"
    echo "$resp" | sed 's/^/      /'
    return 1
  fi
}

echo "Submitting 19 packages to Packagist as user '${PACKAGIST_USERNAME}'..."
fail=0
for pkg in "${PACKAGES[@]}"; do
  submit_one "$pkg" || fail=$((fail + 1))
  sleep 1  # be gentle with the API
done

echo ""
if [ "$fail" -gt 0 ]; then
  echo "Done with $fail failure(s)."
  exit 1
fi
echo "Done. All packages submitted (or already existed)."
echo ""
echo "Next: verify each package fetched its first version automatically:"
echo "  curl -sI https://packagist.org/packages/arqel-dev/core.json"
