#!/bin/bash

# restart_servers.sh - Restart WhimsicalFrog development server
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
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}WhimsicalFrog Server Restart${NC}"

########################################
# Stop any existing servers
########################################
########################################
echo -e "${YELLOW}Stopping any existing PHP servers...${NC}"
# Kill any Vite dev servers
pkill -f "vite" 2>/dev/null || true
pkill -f "npm run dev" 2>/dev/null || true
# Wait for ports to be released
sleep 1

# Kill all PHP dev servers
pkill -f "php -S localhost:" 2>/dev/null || true

# Wait for ports to be released
sleep 2

########################################
# Start PHP server on port $PORT
########################################
PORT=8080
VITE_PORT=5176
echo -e "${GREEN}Starting PHP dev server on http://localhost:$PORT${NC}"

# Start server in background
php -S localhost:$PORT -t . > logs/php_server.log 2>&1 &
PHP_PID=$!

# Wait a moment and check if it started successfully
sleep 2
if kill -0 $PHP_PID 2>/dev/null; then
  echo -e "${GREEN}✓ PHP server started successfully (PID $PHP_PID)${NC}"
  echo -e "${GREEN}✓ Website available at: http://localhost:$PORT${NC}"
else
  echo -e "${RED}✗ Failed to start PHP server${NC}"
  echo -e "${RED}Check logs/php_server.log for details${NC}"
  exit 1
fi

########################################
# Start Vite dev server on port $VITE_PORT
########################################

echo -e "${GREEN}Starting Vite dev server on http://localhost:$VITE_PORT${NC}"
# Ensure Node modules are installed (skip if already present)
if [ ! -d "node_modules" ]; then
  echo -e "${YELLOW}node_modules not found – installing dependencies (this may take a while)...${NC}"
  npm install --silent
fi
npm run dev -- --port $VITE_PORT > logs/vite_server.log 2>&1 &
VITE_PID=$!
# Wait a moment and check if it started successfully
sleep 3
if kill -0 $VITE_PID 2>/dev/null; then
  echo -e "${GREEN}✓ Vite dev server started successfully (PID $VITE_PID)${NC}"
  echo -e "${GREEN}✓ Frontend hot-reload available at: http://localhost:$VITE_PORT${NC}"
else
  echo -e "${RED}✗ Failed to start Vite dev server${NC}"
  echo -e "${RED}Check logs/vite_server.log for details${NC}"
fi

########################################
# Ensure MySQL is running
########################################
echo -e "${YELLOW}Checking MySQL status...${NC}"
# First, check if something is already listening on the default MySQL port.
if lsof -ti :3306 >/dev/null 2>&1; then
  echo -e "${GREEN}✓ MySQL is already running${NC}"
else
  echo -e "${YELLOW}Starting MySQL...${NC}"

  STARTED=false

  # 1) Try Homebrew service if the formula exists.
  if command -v brew >/dev/null 2>&1 && brew list --formula | grep -q "^mysql$"; then
    brew services start mysql > /dev/null 2>&1 && STARTED=true
  fi

  # 2) Fallback to native macOS installer paths if Homebrew method didn’t work.
  if ! $STARTED; then
    if [ -x "/usr/local/mysql/support-files/mysql.server" ]; then
      echo 'Palz2516!' | sudo -S /usr/local/mysql/support-files/mysql.server start > /dev/null 2>&1 && STARTED=true
    elif [ -x "/usr/local/mysql/bin/mysql.server" ]; then
      echo 'Palz2516!' | sudo -S /usr/local/mysql/bin/mysql.server start > /dev/null 2>&1 && STARTED=true
    fi
  fi

  # 3) Final status message.
  if $STARTED; then
    echo -e "${GREEN}✓ MySQL started${NC}"
  else
    echo -e "${RED}✗ Failed to start MySQL${NC}"
  fi
fi

echo -e "\n${GREEN}=== WhimsicalFrog Server Restart Complete ===${NC}"
echo -e "${GREEN}Your website is now running at: http://localhost:$PORT${NC}"
echo -e "${YELLOW}To stop the server: pkill -f 'php -S localhost:$PORT'${NC}"