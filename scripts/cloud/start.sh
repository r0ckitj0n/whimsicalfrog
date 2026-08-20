#!/usr/bin/env bash
# Cloud Agent start phase for Whimsical Frog.
# Per-boot service startup. Brings up MariaDB, ensures the dev database/schema,
# starts the PHP backend (:8080), and runs the Vite dev server (:5176) in the
# foreground so this process stays attached.
set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

log() { printf '[start] %s\n' "$*"; }
mkdir -p logs

# --- MariaDB ----------------------------------------------------------------
sudo mkdir -p /var/run/mysqld /var/lib/mysql
sudo chown -R mysql:mysql /var/run/mysqld /var/lib/mysql

# Initialize the data directory on first boot from a bare base image.
if [ ! -d /var/lib/mysql/mysql ]; then
  log "Initializing MariaDB data directory"
  sudo mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null 2>&1 || true
fi

if ! sudo mysqladmin ping >/dev/null 2>&1; then
  log "Starting MariaDB"
  sudo sh -c 'nohup mariadbd-safe --skip-syslog >>/workspace/logs/mariadb.log 2>&1 &'
  for _ in $(seq 1 60); do
    sudo mysqladmin ping >/dev/null 2>&1 && break
    sleep 1
  done
fi
sudo mysqladmin ping >/dev/null 2>&1 && log "MariaDB is up" || { log "MariaDB failed to start"; exit 1; }

# --- Database + user + schema (idempotent) ----------------------------------
sudo mariadb <<'SQL'
CREATE DATABASE IF NOT EXISTS whimsicalfrog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING '';
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED VIA mysql_native_password USING '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL

log "Bootstrapping dev database schema"
php scripts/db/bootstrap_dev_db.php || log "DB bootstrap reported a warning (continuing)"

# --- Enable Vite dev mode for the PHP backend -------------------------------
# `.disable-vite-dev` is committed in the repo and forces the PHP router into
# production mode (serving dist/). For local development we want the backend to
# proxy to the Vite dev server, so remove the runtime flag on boot. This does
# not alter the tracked file's role for production deploys.
rm -f .disable-vite-dev

# --- PHP backend (:8080) ----------------------------------------------------
if ! curl -s -o /dev/null http://localhost:8080/ 2>/dev/null; then
  log "Starting PHP backend on :8080"
  nohup php -S localhost:8080 router.php >>logs/php_server.log 2>&1 &
fi

# --- Vite dev server (:5176), foreground ------------------------------------
log "Starting Vite dev server on :5176"
exec npm run dev
