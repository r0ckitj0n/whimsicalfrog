#!/bin/bash
set -euo pipefail

# scripts/dev.sh - Start WhimsicalFrog in DEV mode (PHP + Vite with HMR)
# Usage: ./scripts/dev.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/.."

# Note: Now using .cursorrules with Antigravity (Windsurf sync removed)

PORT=${PORT:-8080}
VITE_PORT=${VITE_DEV_PORT:-5176}

mkdir -p logs

# Enable dev mode: remove flag and unset env disable
rm -f .disable-vite-dev || true
# Provide dev origin to proxy if not set
: "${WF_VITE_ORIGIN:=http://localhost:${VITE_PORT}}"
export WF_VITE_ORIGIN

echo "🐸 DEV MODE"
echo "  PHP:  http://localhost:${PORT}"
echo "  Vite: ${WF_VITE_ORIGIN}"

# Restart both servers (PHP + Vite) using standardized script
./scripts/restart_servers.sh
