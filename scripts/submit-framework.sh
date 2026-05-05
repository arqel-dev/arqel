#!/usr/bin/env bash
# Submit arqel-dev/framework to Packagist + add OAuth-style webhook.
# Usage (from shell with PACKAGIST_API_TOKEN + PACKAGIST_USERNAME exported):
#   ./scripts/submit-framework.sh

set -euo pipefail

: "${PACKAGIST_API_TOKEN:?PACKAGIST_API_TOKEN required}"
: "${PACKAGIST_USERNAME:?PACKAGIST_USERNAME required}"

REPO_URL="https://github.com/arqel-dev/framework"

echo "Submitting arqel-dev/framework..."
resp=$(curl -fsS -X POST \
  "https://packagist.org/api/create-package?username=${PACKAGIST_USERNAME}&apiToken=${PACKAGIST_API_TOKEN}" \
  -H "Content-Type: application/json" \
  -d "{\"repository\":{\"url\":\"${REPO_URL}\"}}" \
  -w '\nHTTP_STATUS:%{http_code}')
status=$(echo "$resp" | grep -oE 'HTTP_STATUS:[0-9]+' | cut -d: -f2)
if [ "$status" = "201" ] || [ "$status" = "202" ]; then
  echo "  OK ($status)"
else
  echo "  FAILED:"; echo "$resp"; exit 1
fi

echo ""
echo "Triggering immediate update..."
curl -fsS -X POST \
  "https://packagist.org/api/update-package?username=${PACKAGIST_USERNAME}&apiToken=${PACKAGIST_API_TOKEN}" \
  -H 'Content-Type: application/json' \
  -d "{\"repository\":{\"url\":\"https://packagist.org/packages/arqel-dev/framework\"}}" \
  -w '\nHTTP_STATUS:%{http_code}\n'

echo ""
echo "Done. Check: curl -s https://packagist.org/packages/arqel-dev/framework.json | jq '.package.versions | keys'"
