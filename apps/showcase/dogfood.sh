#!/usr/bin/env bash
# apps/showcase/dogfood.sh — isolated dogfood stack driver
set -e
cd "$(dirname "$0")"
DC="docker compose -p arqel-dogfood -f compose.dogfood.yml"

# Wait until the app container can reach postgres before running migrations.
wait_for_db() {
  echo "Waiting for postgres to accept connections..."
  for i in $(seq 1 30); do
    if $DC exec -T app php -r 'exit(@fsockopen("db", 5432) ? 0 : 1);' >/dev/null 2>&1; then
      echo "postgres is ready."
      return 0
    fi
    sleep 2
  done
  echo "postgres did not become ready in time." >&2
  return 1
}

case "${1:-help}" in
  up)    $DC up -d --build ;;
  down)  $DC down ;;
  reset) $DC down -v ;;
  fresh)
    $DC up -d --build
    # vendor/ is host-mounted (Linux host == Linux container, binaries match) and the
    # arqel-dev/* path-repo symlinks resolve via the /var/packages mount. Only install
    # if vendor/ is genuinely missing; otherwise just refresh the autoloader.
    $DC exec -T app sh -c '[ -d vendor/laravel ] && composer dump-autoload --no-interaction || composer install --no-interaction --no-progress'
    wait_for_db
    # APP_KEY comes from .env.dogfood (the Docker env_file); no key:generate needed.
    $DC exec -T app sh -c "php artisan migrate:fresh --seed --force && php artisan storage:link --force"
    ;;
  test)  $DC exec -T app php artisan test ;;
  e2e)   APP_BASE_URL=http://localhost:8090 pnpm --filter @arqel-dev/showcase exec playwright test ;;
  logs)  $DC logs -f "${2:-}" ;;
  sh)    $DC exec app sh ;;
  *)     echo "usage: ./dogfood.sh {up|down|reset|fresh|test|e2e|logs|sh}" ;;
esac
