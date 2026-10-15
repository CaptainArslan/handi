#!/usr/bin/env bash
# Zero-surprise deploy for a single VPS. Run on the server from /var/www/handi:
#   bash backend/deploy/deploy.sh
# Laravel Forge users: paste the commands below into the site's Deploy Script instead.
set -euo pipefail

cd "$(dirname "$0")/../.."          # repo root
git pull --ff-only origin main

cd backend
php artisan down --retry=30 || true

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan storage:link || true
php artisan filament:optimize
php artisan optimize               # config, routes, views, events cache
php artisan queue:restart || true  # workers pick up the new code

php artisan up
echo "Deployed $(git rev-parse --short HEAD)"
