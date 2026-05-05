#!/usr/bin/env bash
#
# Arqel setup script for `admin-arqel`.
# Review every command before running.

set -euo pipefail

echo "==> Creating Laravel application: admin-arqel"
laravel new admin-arqel --breeze
cd admin-arqel

echo "==> Installing arqel/arqel"
composer require arqel/arqel

echo "==> Running arqel:install"
php artisan arqel:install

echo "==> Installing JS deps"
pnpm install
echo '==> Dark mode preset enabled (configure via config/arqel.php)'

echo "==> Done. Next: cd admin-arqel && php artisan serve"
