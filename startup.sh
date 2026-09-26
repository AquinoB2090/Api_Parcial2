#!/usr/bin/env bash
set -e

APP_ROOT="/home/site/wwwroot"
PUBLIC_ROOT="$APP_ROOT/public"
NGINX_CONFIGS=(
    "/etc/nginx/sites-available/default"
    "/etc/nginx/sites-enabled/default"
)

echo "Configuring Laravel for Azure App Service..."

if [ -f "$APP_ROOT/default" ]; then
    echo "Installing repository NGINX config"
    cp "$APP_ROOT/default" /etc/nginx/sites-available/default
    cp "$APP_ROOT/default" /etc/nginx/sites-enabled/default
else
    for config in "${NGINX_CONFIGS[@]}"; do
        if [ -f "$config" ]; then
            echo "Updating $config"
            sed -i -E "s#^[[:space:]]*root[[:space:]]+[^;]+;#        root $PUBLIC_ROOT;#g" "$config"
            sed -i -E 's#^[[:space:]]*try_files[[:space:]].*;#            try_files $uri $uri/ /index.php?$args;#g' "$config"
        fi
    done
fi

if [ -L /etc/nginx/sites-enabled/default ]; then
    ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default
elif [ -f /etc/nginx/sites-enabled/default ]; then
    cp /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default
fi

for config in "${NGINX_CONFIGS[@]}"; do
    if [ -f "$config" ]; then
        echo "Active NGINX config in $config:"
        grep -n "root\\|try_files" "$config" || true
    fi
done

if command -v nginx >/dev/null 2>&1; then
    nginx -t
fi

service nginx restart || nginx -s reload || true

cd "$APP_ROOT"

if [ -f artisan ]; then
    chmod +x "$APP_ROOT/App_Data/jobs/continuous/subastas/run.sh"
    export VEHICLE_PHOTO_ROOT="${VEHICLE_PHOTO_ROOT:-/home/data/vehicle-photos}"
    mkdir -p "$VEHICLE_PHOTO_ROOT"
    chmod 775 "$VEHICLE_PHOTO_ROOT"
    mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache
    chmod -R 775 storage bootstrap/cache || true
    if id www-data >/dev/null 2>&1; then
        chown -R www-data:www-data "$VEHICLE_PHOTO_ROOT" storage bootstrap/cache
    fi
    php artisan config:clear || true
    php artisan route:clear || true
    php artisan view:clear || true
    php artisan cache:clear || true
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
fi

# El comando personalizado reemplaza el arranque predeterminado de Oryx.
# Mantener PHP-FPM en primer plano para atender las solicitudes de Nginx.
exec php-fpm -F
