#!/usr/bin/env bash
#
# Deploy script para Ingleball.
#
# Uso:
#   sudo ./deploy.sh                 # release + restart del servicio
#
# Variables de entorno (opcionales):
#   APP_DIR   ruta de la app (default: el directorio de este script)
#   APP_USER  usuario del sistema que ejecuta la app (default: www-data)
#   PHP_BIN   binario de PHP (default: php — en Debian 13 apunta a php8.4)
#   SERVICE   unidad systemd a reiniciar (default: ingleball.service)
#
# Requerimientos (LXC Debian / Proxmox):
#   - composer + extensiones php8.4 (sqlite3, mbstring, xml, curl)
#   - acceso a systemctl (ejecutar como root o con sudo)

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_USER="${APP_USER:-www-data}"
PHP_BIN="${PHP_BIN:-php}"
SERVICE="${SERVICE:-ingleball.service}"

run_app() {
    sudo -u "$APP_USER" "$PHP_BIN" "$APP_DIR/artisan" "$@"
}

cd "$APP_DIR"

echo "==> fix vendor ownership (composer unprivileged)"
chown -R "$APP_USER":"$APP_USER" "$APP_DIR/vendor"

echo "==> composer install"
sudo -u "$APP_USER" composer install --no-dev --optimize-autoloader

echo "==> migrate"
run_app migrate --force

echo "==> caches"
run_app config:cache
run_app route:cache
run_app view:cache

echo "==> restart ${SERVICE}"
sudo systemctl restart "$SERVICE"

echo "==> Deploy OK"
