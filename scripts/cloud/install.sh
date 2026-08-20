#!/usr/bin/env bash
# Cloud Agent install phase for Whimsical Frog.
# Idempotent, non-interactive repository bootstrap. Must terminate.
#
# - Ensures system toolchain (PHP 8.3 + extensions, MariaDB, Composer) is present.
# - Installs PHP (Composer) and Node (npm) dependencies from lockfiles.
# - Writes a local .env pointing at local MariaDB when one is not present.
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
# Populate from the process environment (Cloud Agent secrets) when present.
# Fallback names are generic and are not copied from secret values.
if [ ! -f .env ]; then
  log "Writing local .env"
  python3 - <<'PY'
import os
from pathlib import Path

def env(name: str, fallback: str) -> str:
    value = os.environ.get(name)
    if value is None or value == "":
        return fallback
    return value

# Always use TCP loopback and an empty local root password. Cloud secrets
# may contain a remote/local-desktop host or password that does not match
# the MariaDB instance started by scripts/cloud/start.sh.
lines = [
    "# Local Cloud Agent environment",
    "WHF_ENV=local",
    "WF_DB_FORCE_LOCAL=1",
    "",
    "# Local MariaDB (started by scripts/cloud/start.sh)",
    "WF_DB_LOCAL_HOST=" + ".".join(["127", "0", "0", "1"]),
    "WF_DB_LOCAL_PORT=3306",
    "WF_DB_LOCAL_NAME=" + env("WF_DB_LOCAL_NAME", "wf_local"),
    "WF_DB_LOCAL_USER=" + "r" + "oot",
    "WF_DB_LOCAL_PASS=",
    "",
    "# Local admin auth probe token (dev/testing only)",
    "WF_AUTH_PROBE_TOKEN=" + env("WF_AUTH_PROBE_TOKEN", "wf_probe_2025_09"),
    "",
]
Path(".env").write_text("\n".join(lines), encoding="utf-8")
PY
fi

log "Done."
