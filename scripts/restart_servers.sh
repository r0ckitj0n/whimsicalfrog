#!/bin/bash

# restart_servers.sh - Restart local development servers (PHP built-in dev server + optional MySQL)
# Usage: ./restart_servers.sh
# Make sure the script is executable:  chmod +x restart_servers.sh

set -e

# Change to project root (one level up from scripts directory)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/.."
# Ensure logs directory exists for server logs
mkdir -p logs

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

########################################
# Restart PHP built-in dev server
########################################
PORT=8000
PHP_PID=$(lsof -ti :$PORT || true)
if [[ -n "$PHP_PID" ]]; then
  echo -e "${YELLOW}Stopping existing PHP dev server on port $PORT (PID $PHP_PID)${NC}"
  kill "$PHP_PID" 2>/dev/null || true
  # Fallback: kill any lingering PHP dev server processes listening on port
  pkill -f "php -S localhost:$PORT" 2>/dev/null || true
  # Wait for port to be released
  timeout=5
  while lsof -ti :$PORT >/dev/null && [ $timeout -gt 0 ]; do
    sleep 1
    timeout=$((timeout - 1))
  done
  # give the OS a moment to release the port
  sleep 1
fi

echo -e "${GREEN}Starting PHP dev server on http://localhost:$PORT${NC}"
# Run in background; output redirected to logs/php_server.log
php -S localhost:$PORT -t "$SCRIPT_DIR/.." > logs/php_server.log 2>&1 &
PHP_NEW_PID=$!
echo -e "${GREEN}PHP dev server started (PID $PHP_NEW_PID). Logs -> logs/php_server.log${NC}"

########################################
# Ensure MySQL (Homebrew service) is running
########################################
if command -v brew >/dev/null 2>&1; then
  echo -e "${YELLOW}Ensuring MySQL brew service is running${NC}"
  # Try restarting; if not installed or not running, start it
  brew services restart mysql > /dev/null 2>&1 && echo -e "${GREEN}MySQL restarted.${NC}" \
    || (brew services start mysql > /dev/null 2>&1 && echo -e "${GREEN}MySQL started.${NC}")
fi

echo -e "${GREEN}All requested services restarted.${NC}"
# Wait for MySQL to accept connections (up to 10s)
echo -e "${YELLOW}Waiting for MySQL to accept connections...${NC}"
for i in {1..10}; do
  if lsof -ti :3306 >/dev/null; then
    echo -e "${GREEN}MySQL is accepting connections.${NC}"
    break
  fi
  sleep 1
done 