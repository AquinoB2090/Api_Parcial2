#!/usr/bin/env bash
set -e

APP_ROOT="/home/site/wwwroot"
PUBLIC_ROOT="$APP_ROOT/public"
NGINX_CONFIGS=(
    "/etc/nginx/sites-available/default"
    "/etc/nginx/sites-enabled/default"
)

echo "Configuring Laravel for Azure App Service..."

for config in "${NGINX_CONFIGS[@]}"; do
    if [ -f "$config" ]; then
        echo "Updating $config"
        sed -i -E "s#^[[:space:]]*root[[:space:]]+[^;]+;#        root $PUBLIC_ROOT;#g" "$config"
        sed -i -E 's#^[[:space:]]*try_files[[:space:]].*;#            try_files $uri $uri/ /index.php?$args;#g' "$config"
    fi
done

if command -v nginx >/dev/null 2>&1; then
    nginx -t
fi

service nginx reload || nginx -s reload || true

cd "$APP_ROOT"

if [ -f artisan ]; then
    php artisan route:clear || true
    php artisan config:cache || true
fi
