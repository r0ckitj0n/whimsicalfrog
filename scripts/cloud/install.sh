#!/usr/bin/env bash
# Cloud Agent install phase for Whimsical Frog.
# Idempotent, non-interactive repository bootstrap. Must terminate.
#
# - Ensures system toolchain (PHP 8.3 + extensions, MariaDB, Composer) is present.
#   These normally come from the prebuilt environment snapshot; the guards below
#   self-heal a base image that is missing them.
# - Installs PHP (Composer) and Node (npm) dependencies.
# - Writes a local .env pointing at the local MariaDB when one is not present.
set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

log() { printf '[install] %s\n' "$*"; }

# --- System toolchain (self-healing guard) ---------------------------------
NEED_APT=0
command -v php >/dev/null 2>&1 || NEED_APT=1
command -v mariadbd >/dev/null 2>&1 || command -v mysqld >/dev/null 2>&1 || NEED_APT=1

if [ "$NEED_APT" -eq 1 ]; then
  log "Installing system packages (php, mariadb, extensions)"
  sudo DEBIAN_FRONTEND=noninteractive apt-get update -y
  sudo DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
    php-cli php-mysql php-mbstring php-xml php-curl php-gd php-zip php-bcmath php-intl \
    mariadb-server mariadb-client unzip
fi

if ! command -v composer >/dev/null 2>&1; then
  log "Installing Composer"
  php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
  sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi

# --- PHP dependencies -------------------------------------------------------
log "composer install"
composer install --no-interaction --no-progress --prefer-dist

# --- Node dependencies ------------------------------------------------------
log "npm ci"
npm ci

# --- Local environment file -------------------------------------------------
if [ ! -f .env ]; then
  log "Writing local .env"
  cat > .env <<'ENV'
# Local development environment (Cloud Agent)
WHF_ENV=local
WF_DB_FORCE_LOCAL=1

# Local MariaDB (root, empty password)
WF_DB_LOCAL_HOST=127.0.0.1
WF_DB_LOCAL_PORT=3306
WF_DB_LOCAL_NAME=whimsicalfrog
WF_DB_LOCAL_USER=root
WF_DB_LOCAL_PASS=

# Local admin auth probe token (dev/testing only)
WF_AUTH_PROBE_TOKEN=wf_probe_2025_09
ENV
fi

log "Done."
