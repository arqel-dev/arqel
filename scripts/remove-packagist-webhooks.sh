#!/usr/bin/env bash
# Remove the legacy Packagist webhooks (added by setup-packagist-webhooks.sh)
# from each arqel-dev/<pkg> sub-repo.
#
# Use this AFTER confirming that your Packagist account has GitHub OAuth
# connected — Packagist will auto-update via OAuth without these webhooks,
# and dropping the legacy webhook clears the "Legacy Auto-Update" warning.
#
# Usage: ./scripts/remove-packagist-webhooks.sh

set -euo pipefail

PACKAGES=(
  actions ai audit auth cli core export
  fields fields-advanced form marketplace mcp
  nav realtime table tenant versioning widgets workflow
)

remove_one() {
  local pkg="$1"
  local repo="arqel-dev/$pkg"

  local hook_id
  hook_id=$(gh api "/repos/$repo/hooks" --jq '.[] | select(.config.url | test("packagist.org/api/github")) | .id' 2>/dev/null | head -1 || true)

  if [ -z "$hook_id" ]; then
    echo "  [absent]  $repo — no Packagist webhook found"
    return 0
  fi

  echo "  [delete]  $repo (hook id $hook_id)"
  if gh api -X DELETE "/repos/$repo/hooks/$hook_id" >/dev/null 2>&1; then
    echo "    OK"
  else
    echo "    FAILED"
    return 1
  fi
}

echo "Removing legacy Packagist webhooks from 19 sub-repos..."
fail=0
for pkg in "${PACKAGES[@]}"; do
  remove_one "$pkg" || fail=$((fail + 1))
  sleep 1
done

echo ""
[ "$fail" -gt 0 ] && { echo "Done with $fail failure(s)."; exit 1; }
echo "Done. Packagist will continue to auto-update via OAuth integration."
