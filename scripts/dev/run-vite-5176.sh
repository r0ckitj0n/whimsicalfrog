#!/bin/bash
# Hardened Vite launcher for localhost:5176
set -euo pipefail
cd "$(dirname "$0")/../.."

# Standardize env
export VITE_DEV_PORT=5176
export VITE_HMR_PORT=5176
# Allow override from caller; default to localhost if not provided
export WF_VITE_ORIGIN="${WF_VITE_ORIGIN:-http://localhost:5176}"

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

# Write hot file to match origin exactly (scheme://host:port)
printf '%s' "${WF_VITE_ORIGIN%/}" > hot

# Derive bind host from WF_VITE_ORIGIN (supports IPv6 [::1])
HOST_PART="127.0.0.1"
if [ -z "$HOST_PART" ]; then HOST_PART="127.0.0.1"; fi

# Start vite in foreground (pm2 will daemonize this script)
echo "[run-vite-5176] Starting Vite on ${WF_VITE_ORIGIN} (host=${HOST_PART})"
echo "[run-vite-5176] Current directory: $(pwd)"
echo "[run-vite-5176] Looking for vite.config.ts in: $(pwd)"
ls -l vite.config.ts || echo "[run-vite-5176] WARNING: vite.config.ts not found in $(pwd)"

exec npx vite --config vite.config.ts --host "$HOST_PART" --port 5176 --strictPort --clearScreen false --debug
