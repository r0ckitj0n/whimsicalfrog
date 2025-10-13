#!/usr/bin/env bash
set -euo pipefail

# Drops legacy DB-driven CSS tables (css_variables, global_css_rules) in the LOCAL database.
# Uses credentials from config/my.cnf [client] section.

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
SQL_FILE="$ROOT_DIR/scripts/dev/drop_legacy_css_tables.sql"
MY_CNF="$ROOT_DIR/config/my.cnf"
DB_NAME="whimsicalfrog"

if [[ ! -f "$SQL_FILE" ]]; then
  echo "SQL file not found: $SQL_FILE" >&2
  exit 1
fi

if [[ ! -f "$MY_CNF" ]]; then
  echo "MySQL config not found: $MY_CNF" >&2
  echo "Create config/my.cnf with [client] user/password/host." >&2
  exit 1
fi

echo "About to drop legacy CSS tables in database: $DB_NAME"
mysql --defaults-extra-file="$MY_CNF" "$DB_NAME" < "$SQL_FILE"
echo "Done."
