#!/usr/bin/env bash
set -euo pipefail
cd /home/site/wwwroot
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
