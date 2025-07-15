#!/bin/bash

# restart_servers.sh - Restart local development servers (PHP built-in dev server + optional MySQL)
# Usage: ./restart_servers.sh
# Make sure the script is executable:  chmod +x restart_servers.sh

set -e

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
  # give the OS a moment to release the port
  sleep 1
fi

echo -e "${GREEN}Starting PHP dev server on http://localhost:$PORT${NC}"
# Run in background; output redirected to logs/php_server.log
php -S localhost:$PORT > logs/php_server.log 2>&1 &
PHP_NEW_PID=$!
echo -e "${GREEN}PHP dev server started (PID $PHP_NEW_PID). Logs -> logs/php_server.log${NC}"

########################################
# Restart MySQL (Homebrew service) – optional
########################################
if command -v brew >/dev/null 2>&1; then
  if brew services list | grep -q "mysql.*started"; then
    echo -e "${YELLOW}Restarting MySQL brew service${NC}"
    brew services restart mysql > /dev/null 2>&1 && echo -e "${GREEN}MySQL restarted.${NC}"
  fi
fi

echo -e "${GREEN}All requested services restarted.${NC}" 