#!/bin/bash

# WhimsicalFrog Server Monitor
# This script monitors and manages the PHP web server for the WhimsicalFrog website.
# Note: Node.js dependency has been eliminated - all APIs now run in PHP.

# Configuration
WEBSITE_DIR="/Users/jongraves/Documents/Websites/WhimsicalFrog"
PHP_PORT=8080
# Vite dev server port
VITE_PORT=5176
LOG_FILE="$WEBSITE_DIR/logs/monitor.log"
CHECK_INTERVAL=60  # Check every 60 seconds

# Colors for terminal output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Log function
log() {
  local message="$1"
  local timestamp=$(date "+%Y-%m-%d %H:%M:%S")
  echo -e "${timestamp} - ${message}" >> "$LOG_FILE"
  echo -e "${timestamp} - ${message}"
}

# Check if a port is in use
is_port_in_use() {
  local port=$1
  lsof -i :$port >/dev/null 2>&1
  return $?
}

# Check if a server is responding
is_server_responding() {
  local url=$1
  curl -s --head --request GET $url | grep "200 OK" >/dev/null 2>&1
  return $?
}

# Node.js server functions removed - no longer needed
# All APIs now run in PHP

# Start PHP server
start_php_server() {
  cd "$WEBSITE_DIR"
  log "${BLUE}Starting PHP web server on port $PHP_PORT...${NC}"
  php -S localhost:$PHP_PORT -t . > logs/php_server.log 2>&1 &
  sleep 2
  if is_port_in_use $PHP_PORT; then
    log "${GREEN}PHP web server started successfully${NC}"
    return 0
  else
    log "${RED}Failed to start PHP web server${NC}"
    return 1
  fi
}

# Stop Node.js server function removed - no longer needed

# ===================== VITE DEV SERVER FUNCTIONS =====================

# Start Vite dev server
start_vite_server() {
  cd "$WEBSITE_DIR"
  log "${BLUE}Starting Vite dev server on port $VITE_PORT...${NC}"
  # Ensure node_modules are installed; skip if already done
  if [ ! -d "node_modules" ]; then
    log "${YELLOW}node_modules not found – installing dependencies (this may take a while)...${NC}"
    npm install --silent
  fi
  # Start vite (npm run dev) in background with custom port
  npm run dev -- --port $VITE_PORT > logs/vite_server.log 2>&1 &
  sleep 3
  if is_port_in_use $VITE_PORT; then
    log "${GREEN}Vite dev server started successfully${NC}"
    return 0
  else
    log "${RED}Failed to start Vite dev server${NC}"
    return 1
  fi
}

# Stop Vite dev server
stop_vite_server() {
  log "${BLUE}Stopping Vite dev server...${NC}"
  pkill -f "vite" || true
  pkill -f "npm run dev" || true
  sleep 2
  if ! is_port_in_use $VITE_PORT; then
    log "${GREEN}Vite dev server stopped successfully${NC}"
    return 0
  else
    log "${RED}Failed to stop Vite dev server${NC}"
    return 1
  fi
}

# Check & restart Vite server if needed
check_and_restart_vite() {
  if ! is_port_in_use $VITE_PORT; then
    log "${YELLOW}Vite dev server is not running. Restarting...${NC}"
    start_vite_server
  elif ! is_server_responding "http://localhost:$VITE_PORT"; then
    log "${YELLOW}Vite dev server not responding. Restarting...${NC}"
    stop_vite_server
    start_vite_server
  else
    log "${GREEN}Vite dev server is running correctly${NC}"
  fi
}

# ===================== EXISTING PHP FUNCTIONS =====================

# Stop PHP server
stop_php_server() {
  log "${BLUE}Stopping PHP web server...${NC}"
  pkill -f "php -S localhost:$PHP_PORT" || true
  sleep 1
  if ! is_port_in_use $PHP_PORT; then
    log "${GREEN}PHP web server stopped successfully${NC}"
    return 0
  else
    log "${RED}Failed to stop PHP web server${NC}"
    return 1
  fi
}

# Node.js monitoring function removed - no longer needed

# Check and restart PHP server if needed
check_and_restart_php() {
  if ! is_port_in_use $PHP_PORT; then
    log "${YELLOW}PHP web server is not running. Restarting...${NC}"
    start_php_server
  elif ! is_server_responding "http://localhost:$PHP_PORT"; then
    log "${YELLOW}PHP web server is not responding. Restarting...${NC}"
    stop_php_server
    start_php_server
  else
    log "${GREEN}PHP web server is running correctly${NC}"
  fi
}

# Start all servers
start_all() {
  log "${BLUE}Starting WhimsicalFrog PHP server...${NC}"
  start_php_server
  log "${BLUE}Starting Vite dev server...${NC}"
  start_vite_server
  show_access_info
}

# Stop all servers
stop_all() {
  log "${BLUE}Stopping WhimsicalFrog Vite dev server...${NC}"
  stop_vite_server
  log "${BLUE}Stopping WhimsicalFrog PHP server...${NC}"
  stop_php_server
}

# Restart all servers
restart_all() {
  log "${BLUE}Restarting all WhimsicalFrog servers...${NC}"
  stop_all
  sleep 3
  start_all
}

# Check status of PHP server
check_status() {
  echo -e "${BLUE}=== WhimsicalFrog Server Status ===${NC}"
  
  if is_port_in_use $PHP_PORT; then
    echo -e "${GREEN}✓ PHP web server is running on port $PHP_PORT${NC}"
  else
    echo -e "${RED}✗ PHP web server is NOT running${NC}"
  fi
  
  show_access_info
}

# Show access information
show_access_info() {
  echo -e "\n${BLUE}=== Access Information ===${NC}"
  echo -e "${GREEN}Website:${NC} http://localhost:$PHP_PORT"
  echo -e "${GREEN}Frontend Hot-Reload (Vite):${NC} http://localhost:$VITE_PORT"
  echo -e "${GREEN}API Endpoints (all PHP-based):${NC}"
  echo -e "  - Items: http://localhost:$PHP_PORT/api/get_items.php"
  echo -e "  - Inventory: http://localhost:$PHP_PORT/api/inventory.php"
  echo -e "  - Orders: http://localhost:$PHP_PORT/api/orders.php"
  echo -e "  - Users: http://localhost:$PHP_PORT/api/users.php"
  echo -e "\n${YELLOW}To set up automatic monitoring, add this to your crontab:${NC}"
  echo -e "*/5 * * * * $WEBSITE_DIR/Scripts/server_monitor.sh monitor >> $LOG_FILE 2>&1"
  echo -e "${YELLOW}This will check your PHP server every 5 minutes and restart it if needed.${NC}\n"
}

# Monitor PHP server continuously
monitor() {
  log "${BLUE}Starting continuous monitoring (checking every $CHECK_INTERVAL seconds)...${NC}"
  while true; do
    check_and_restart_php
    check_and_restart_vite
    sleep $CHECK_INTERVAL
  done
}

# Monitor PHP server once
monitor_once() {
  log "${BLUE}Checking PHP server status...${NC}"
  check_and_restart_php
}

# Main function
main() {
  # Create log file if it doesn't exist
  touch "$LOG_FILE"
  
  # Process command line arguments
  case "$1" in
    start)
      start_all
      ;;
    stop)
      stop_all
      ;;
    restart)
      restart_all
      ;;
    status)
      check_status
      ;;
    monitor)
      monitor_once
      ;;
    daemon)
      monitor
      ;;
    *)
      echo -e "${BLUE}WhimsicalFrog Server Monitor${NC}"
      echo -e "Usage: $0 {start|stop|restart|status|monitor|daemon}"
      echo -e "  start   - Start all servers"
      echo -e "  stop    - Stop all servers"
      echo -e "  restart - Restart all servers"
      echo -e "  status  - Check server status"
      echo -e "  monitor - Check and restart servers if needed (run once)"
      echo -e "  daemon  - Continuously monitor and restart servers"
      exit 1
      ;;
  esac
}

# Run main function with all arguments
main "$@"
