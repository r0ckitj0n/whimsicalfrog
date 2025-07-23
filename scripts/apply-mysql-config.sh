#!/bin/bash
# scripts/apply-mysql-config.sh
# Copy local config/my.cnf into the Homebrew MySQL location and restart the service.
# Designed for macOS Homebrew installs. Adjust CONF_DEST if your MySQL installation
# lives elsewhere.

set -euo pipefail

CONF_SRC="$(dirname "$0")/../config/my.cnf"
CONF_DEST="/usr/local/mysql/my.cnf"  # Change if different

printf "Applying MySQL config...\n"

if [[ ! -f "$CONF_SRC" ]]; then
  echo "Source config not found: $CONF_SRC" >&2
  exit 1
fi

# Backup existing destination config if present
if [[ -f "$CONF_DEST" ]]; then
  TS="$(date +%Y%m%d-%H%M%S)"
  sudo cp "$CONF_DEST" "${CONF_DEST}.bak.$TS"
  echo "Backed up existing config to ${CONF_DEST}.bak.$TS"
fi

# Ensure parent directory exists
sudo mkdir -p "$(dirname \"$CONF_DEST\")"
# Copy new config
sudo cp "$CONF_SRC" "$CONF_DEST"
echo "Copied $CONF_SRC → $CONF_DEST"

# Restart MySQL (Homebrew)
if command -v brew >/dev/null 2>&1; then
  echo "Restarting MySQL via Homebrew services…"
  brew services restart mysql
else
  # Fallback for native pkg install
  echo "Restarting MySQL using mysql.server script…"
  sudo /usr/local/mysql/support-files/mysql.server restart
fi

echo "MySQL restarted with new configuration."
