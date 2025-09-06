#!/bin/bash
set -euo pipefail

# scripts/prod.sh - Run WhimsicalFrog in PROD mode (PHP only, built assets)
# Usage: ./scripts/prod.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/.."

PORT=${PORT:-8080}

mkdir -p logs

# Disable dev mode via flag and env
touch .disable-vite-dev
export WF_VITE_DISABLE_DEV=1

# Ensure node deps then build assets
if [ ! -d "node_modules" ]; then
  echo "Installing npm dependencies..."
  npm install --silent
fi

echo "Building production assets..."
npm run build

# Stop any running servers on the port and Vite processes
pkill -f "vite" 2>/dev/null || true
pkill -f "npm run dev" 2>/dev/null || true
lsof -ti tcp:$PORT | xargs kill -9 2>/dev/null || true
sleep 1

# Start PHP server in background using router.php
php -S localhost:$PORT -t . router.php > logs/php_server.log 2>&1 &
PHP_PID=$!
sleep 2

if kill -0 $PHP_PID 2>/dev/null; then
  echo "🐸 PROD MODE"
  echo "  PHP:  http://localhost:${PORT} (PID $PHP_PID)"
  echo "  Vite: disabled"
  echo "Logs: logs/php_server.log"
else
  echo "❌ Failed to start PHP server (see logs/php_server.log)"
  exit 1
fi
