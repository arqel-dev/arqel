#!/bin/sh
set -e
# storage/ and bootstrap/cache are host-mounted (owned by the host UID), but
# php-fpm runs as www-data. Make the Laravel-writable dirs writable so Blade view
# compilation (tempnam), cache, and sessions work. Best-effort: dev stack only.
chmod -R a+rwX storage bootstrap/cache 2>/dev/null || true
php-fpm -D
exec nginx -g 'daemon off;'
