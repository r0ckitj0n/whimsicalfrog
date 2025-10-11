#!/bin/bash
# Hardened Vite launcher for localhost:5176
set -euo pipefail
cd "$(dirname "$0")/../.."

# Standardize env
export VITE_DEV_PORT=5176
export VITE_HMR_PORT=5176
export WF_VITE_ORIGIN="http://localhost:5176"

# Ensure logs dir exists
mkdir -p logs

# Kill stale listeners on 5176/5180 (if any)
PIDS=$(lsof -t -nP -iTCP:5176 -sTCP:LISTEN 2>/dev/null || true)
PIDS2=$(lsof -t -nP -iTCP:5180 -sTCP:LISTEN 2>/dev/null || true)
if [ -n "${PIDS}${PIDS2}" ]; then
  echo "[run-vite-5176] Killing stale listeners: $PIDS $PIDS2"
  kill -9 $PIDS $PIDS2 2>/dev/null || true
fi

# Ensure node_modules present (do not force reinstall here)
if [ ! -d node_modules ]; then
  echo "[run-vite-5176] Installing dependencies (including optional for esbuild)"
  npm ci --include=optional || npm install --include=optional
fi

# Write hot file explicitly
printf 'http://localhost:5176' > hot

# Start vite in foreground (pm2 will daemonize this script)
echo "[run-vite-5176] Starting Vite on http://localhost:5176"
exec npx vite --host localhost --port 5176 --strictPort --clearScreen false --debug
