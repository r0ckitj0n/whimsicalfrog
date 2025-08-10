#!/usr/bin/env bash
# fix_mysql_native.sh – Repair native (pkg) MySQL installation on macOS, reset
# credentials, create WhimsicalFrog DB + admin user, and optionally import the
# live_backup.sql dump.  Run once, with sudo.
#
#   sudo ./scripts/fix_mysql_native.sh
#
# Adjust the ROOT_PWD and ADMIN_PWD variables below if you need something other
# than the default Palz2516! password.

set -euo pipefail

### CONFIGURATION
MYSQL_BASE="/usr/local/mysql"          # Change if your pkg MySQL lives elsewhere
DUMP_FILE="live_backup.sql"            # SQL dump to import (optional)
DB_NAME="whimsical_frog"               # Database name to create/overwrite
ROOT_PWD="Palz2516!"                  # New root password
ADMIN_USER="admin"                     # Application user to create
ADMIN_PWD="Palz2516!"                 # Password for $ADMIN_USER

MYSQLD="$MYSQL_BASE/bin/mysqld"
MYSQL="$MYSQL_BASE/bin/mysql"
MYSQL_ADMIN="$MYSQL_BASE/bin/mysqladmin"
SERVER_SCRIPT="$MYSQL_BASE/support-files/mysql.server"

### PRE-FLIGHT CHECKS
if [[ ! -x $MYSQLD ]]; then
  echo "ERROR: mysqld not found at $MYSQLD" >&2
  exit 1
fi
if [[ ! -x $SERVER_SCRIPT ]]; then
  echo "ERROR: mysql.server helper not found at $SERVER_SCRIPT" >&2
  exit 1
fi

function section() {
  echo -e "\n=== $* ==="
}

section "Stopping any running MySQL instances"
sudo $SERVER_SCRIPT stop || true
pkill -f mysqld || true
sleep 3

section "Starting MySQL in emergency (skip-grant-tables) mode"
sudo -u _mysql $MYSQLD --skip-grant-tables --skip-networking --user=_mysql &
SAFE_PID=$!
# Give mysqld time to start
sleep 8

section "Resetting root password, creating admin user, and granting privileges"
$MYSQL -u root <<SQL
FLUSH PRIVILEGES;
ALTER USER 'root'@'localhost' IDENTIFIED BY '${ROOT_PWD}';
CREATE USER IF NOT EXISTS '${ADMIN_USER}'@'localhost' IDENTIFIED BY '${ADMIN_PWD}';
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${ADMIN_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "✓ Credentials and privileges updated"

section "Shutting down emergency mysqld"
sudo kill "$SAFE_PID"
wait "$SAFE_PID" || true
sleep 4

echo "Starting MySQL normally via preference-pane helper"
sudo $SERVER_SCRIPT start
sleep 6

echo "Verifying access as \"$ADMIN_USER\"..."
$MYSQL -u "$ADMIN_USER" -p"$ADMIN_PWD" -e "SELECT NOW() AS connected;" "$DB_NAME"
echo "✓ Login successful"

if [[ -f $DUMP_FILE ]]; then
  section "Importing $DUMP_FILE into $DB_NAME (this may take a while)"
  $MYSQL -u "$ADMIN_USER" -p"$ADMIN_PWD" "$DB_NAME" < "$DUMP_FILE"
  echo "✓ Import complete"
else
  echo "NOTE: $DUMP_FILE not found, skipping import. Copy the dump file next to this script and re-run if needed."
fi

section "All done – MySQL is repaired and running"
echo "  Root password : $ROOT_PWD"
echo "  Admin user    : $ADMIN_USER / $ADMIN_PWD"
echo "  Database      : $DB_NAME"

exit 0
