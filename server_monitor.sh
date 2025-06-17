#!/bin/bash

# WhimsicalFrog Server Monitor
# This script monitors and manages the Node.js API server and PHP web server
# for the WhimsicalFrog website.

# Configuration
WEBSITE_DIR="/Users/jongraves/Documents/Websites/WhimsicalFrog"
NODE_PORT=3000
PHP_PORT=8000
LOG_FILE="$WEBSITE_DIR/monitor.log"
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

# Start Node.js server
start_node_server() {
  cd "$WEBSITE_DIR"
  log "${BLUE}Starting Node.js API server on port $NODE_PORT...${NC}"
  npm start > server.log 2>&1 &
  sleep 3
  if is_port_in_use $NODE_PORT; then
    log "${GREEN}Node.js API server started successfully${NC}"
    return 0
  else
    log "${RED}Failed to start Node.js API server${NC}"
    return 1
  fi
}

# Start PHP server
start_php_server() {
  cd "$WEBSITE_DIR"
  log "${BLUE}Starting PHP web server on port $PHP_PORT...${NC}"
  php -S localhost:$PHP_PORT -t . > php_server.log 2>&1 &
  sleep 2
  if is_port_in_use $PHP_PORT; then
    log "${GREEN}PHP web server started successfully${NC}"
    return 0
  else
    log "${RED}Failed to start PHP web server${NC}"
    return 1
  fi
}

# Stop Node.js server
stop_node_server() {
  log "${BLUE}Stopping Node.js API server...${NC}"
  pkill -f "node.*server.js" || true
  sleep 1
  if ! is_port_in_use $NODE_PORT; then
    log "${GREEN}Node.js API server stopped successfully${NC}"
    return 0
  else
    log "${RED}Failed to stop Node.js API server${NC}"
    return 1
  fi
}

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

# Check and restart Node.js server if needed
check_and_restart_node() {
  if ! is_port_in_use $NODE_PORT; then
    log "${YELLOW}Node.js API server is not running. Restarting...${NC}"
    start_node_server
  elif ! is_server_responding "http://localhost:$NODE_PORT/api/items"; then
    log "${YELLOW}Node.js API server is not responding. Restarting...${NC}"
    stop_node_server
    start_node_server
  else
    log "${GREEN}Node.js API server is running correctly${NC}"
  fi
}

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
  log "${BLUE}Starting all WhimsicalFrog servers...${NC}"
  start_node_server
  start_php_server
  show_access_info
}

# Stop all servers
stop_all() {
  log "${BLUE}Stopping all WhimsicalFrog servers...${NC}"
  stop_node_server
  stop_php_server
}

# Restart all servers
restart_all() {
  log "${BLUE}Restarting all WhimsicalFrog servers...${NC}"
  stop_all
  sleep 2
  start_all
}

# Check status of all servers
check_status() {
  echo -e "${BLUE}=== WhimsicalFrog Server Status ===${NC}"
  
  if is_port_in_use $NODE_PORT; then
    echo -e "${GREEN}✓ Node.js API server is running on port $NODE_PORT${NC}"
  else
    echo -e "${RED}✗ Node.js API server is NOT running${NC}"
  fi
  
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
  echo -e "${GREEN}API Endpoints:${NC}"
  echo -e "  - Items: http://localhost:$NODE_PORT/api/items"
  echo -e "  - Users: http://localhost:$NODE_PORT/api/users"
  echo -e "  - Inventory: http://localhost:$NODE_PORT/api/inventory"
  echo -e "  - Item Groups: http://localhost:$NODE_PORT/api/item-groups"
  echo -e "\n${YELLOW}To set up automatic monitoring, add this to your crontab:${NC}"
  echo -e "*/5 * * * * $WEBSITE_DIR/server_monitor.sh monitor >> $LOG_FILE 2>&1"
  echo -e "${YELLOW}This will check your servers every 5 minutes and restart them if needed.${NC}\n"
}

# Monitor servers continuously
monitor() {
  log "${BLUE}Starting continuous monitoring (checking every $CHECK_INTERVAL seconds)...${NC}"
  while true; do
    check_and_restart_node
    check_and_restart_php
    sleep $CHECK_INTERVAL
  done
}

# Monitor servers once
monitor_once() {
  log "${BLUE}Checking server status...${NC}"
  check_and_restart_node
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
