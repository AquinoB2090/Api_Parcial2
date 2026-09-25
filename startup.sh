#!/usr/bin/env bash
set -e

NGINX_DEFAULT="/etc/nginx/sites-available/default"

if [ -f "$NGINX_DEFAULT" ]; then
    sed -i 's#/home/site/wwwroot;#/home/site/wwwroot/public;#g' "$NGINX_DEFAULT"
    sed -i 's#try_files $uri $uri/ =404;#try_files $uri $uri/ /index.php?$args;#g' "$NGINX_DEFAULT"
    service nginx reload
fi

cd /home/site/wwwroot

if [ -f artisan ]; then
    php artisan config:cache || true
fi
