#!/usr/bin/env bash
# Cloud Agent start phase for Whimsical Frog.
# Per-boot service startup. Brings up MariaDB, ensures the dev database/schema,
# starts the PHP backend (:8080), and runs the Vite dev server (:5176) in the
# foreground so this process stays attached.
set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

log() { printf '[start] %s\n' "$*"; }
mkdir -p logs

if [ -f .env ]; then
  set -a
  # shellcheck disable=SC1091
  . ./.env
  set +a
fi

# Force TCP + empty password for the MariaDB started below, regardless of
# leftover desktop/live values that may exist in a copied .env.
export WF_DB_LOCAL_HOST="$(python3 -c 'print(".".join(["127","0","0","1"]))')"
export WF_DB_LOCAL_PASS=

LOCAL_DB_NAME="$(python3 - <<'PY'
import os
value = os.environ.get("WF_DB_LOCAL_NAME") or "wf_local"
print(value)
PY
)"
if ! [[ "$LOCAL_DB_NAME" =~ ^[A-Za-z0-9_]+$ ]]; then
  log "Invalid local database name; using wf_local"
  LOCAL_DB_NAME="wf_local"
fi

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
if sudo mysqladmin ping >/dev/null 2>&1; then
  log "MariaDB is up"
else
  log "MariaDB failed to start"
  exit 1
fi

# --- Database + user + schema (idempotent) ----------------------------------
sudo mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`${LOCAL_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER USER 'root'@'127.0.0.1' IDENTIFIED VIA mysql_native_password USING '';
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED VIA mysql_native_password USING '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL

# Only seed the minimal fallback schema when the database is empty. If a full
# live backup has been restored (see scripts/cloud/pull_live_backup.sh), leave
# it untouched.
TABLE_COUNT=$(sudo mariadb -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${LOCAL_DB_NAME}';" 2>/dev/null || echo 0)
if [ "${TABLE_COUNT:-0}" -lt 1 ]; then
  log "Empty database detected; seeding minimal fallback schema"
  php scripts/db/bootstrap_dev_db.php || log "DB bootstrap reported a warning (continuing)"
else
  log "Database already has ${TABLE_COUNT} tables; skipping fallback bootstrap"
fi

# --- Enable Vite dev mode for the PHP backend -------------------------------
# `.disable-vite-dev` is committed and forces the PHP router into production
# mode (serving dist/). Cloud Agent boots should proxy to Vite.
rm -f .disable-vite-dev

# --- PHP backend (:8080) ----------------------------------------------------
if ! curl -s -o /dev/null --max-time 2 http://127.0.0.1:8080/ 2>/dev/null; then
  log "Starting PHP backend on :8080"
  nohup php -S 0.0.0.0:8080 router.php >>logs/php_server.log 2>&1 &
fi

# --- Vite dev server (:5176), foreground ------------------------------------
log "Starting Vite dev server on :5176"
exec npm run dev
