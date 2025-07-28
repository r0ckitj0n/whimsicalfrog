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
# Stop any existing PHP servers
########################################
echo -e "${YELLOW}Stopping any existing PHP servers...${NC}"

# Kill all PHP dev servers
pkill -f "php -S localhost:" 2>/dev/null || true

# Wait for ports to be released
sleep 2

########################################
# Start PHP server on port 8080
########################################
PORT=8080
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
# Ensure MySQL is running
########################################
echo -e "${YELLOW}Checking MySQL status...${NC}"
if command -v brew >/dev/null 2>&1; then
  # Check if MySQL is running
  if lsof -ti :3306 >/dev/null 2>&1; then
    echo -e "${GREEN}✓ MySQL is already running${NC}"
  else
    echo -e "${YELLOW}Starting MySQL...${NC}"
    brew services start mysql > /dev/null 2>&1 && echo -e "${GREEN}✓ MySQL started${NC}" \
      || echo -e "${RED}✗ Failed to start MySQL${NC}"
  fi
else
  echo -e "${YELLOW}Homebrew not found, skipping MySQL check${NC}"
fi

echo -e "\n${GREEN}=== WhimsicalFrog Server Restart Complete ===${NC}"
echo -e "${GREEN}Your website is now running at: http://localhost:$PORT${NC}"
echo -e "${YELLOW}To stop the server: pkill -f 'php -S localhost:$PORT'${NC}"