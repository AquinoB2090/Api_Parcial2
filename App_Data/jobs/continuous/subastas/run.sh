#!/usr/bin/env bash
set -e
cd /home/site/wwwroot
exec php artisan subastas:sincronizar --loop
