#!/bin/bash
set -euo pipefail

# scripts/prod.sh - Run WhimsicalFrog in PROD mode (PHP only, built assets)
# Usage: ./scripts/prod.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/.."

PORT=${PORT:-8080}

mkdir -p logs

# Disable dev mode via flag and env
rm -f hot
touch .disable-vite-dev
export WF_VITE_DISABLE_DEV=1
export WF_VITE_MODE=prod

# Ensure node deps then build assets
if [ ! -d "node_modules" ]; then
  echo "Installing npm dependencies..."
  npm install --silent
fi

echo "Building production assets..."
npm run build

# Restart both servers (PHP only in prod mode) using standardized script
./scripts/restart_servers.sh
