#!/bin/bash

# WhimsicalFrog Website Starter
# This script starts the WhimsicalFrog website servers and monitoring daemon

# Set up colors for better readability
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Change to the WhimsicalFrog directory
cd /Users/jongraves/Documents/Websites/WhimsicalFrog

echo -e "${BLUE}=== WhimsicalFrog Website Starter ===${NC}"
echo -e "${BLUE}Checking if servers are already running...${NC}"

# Check if the monitoring script exists, if not display an error
if [ ! -f "./Scripts/server_monitor.sh" ]; then
  echo -e "${RED}Error: Scripts/server_monitor.sh not found!${NC}"
  echo -e "${YELLOW}Please make sure you're running this script from the correct directory.${NC}"
  exit 1
fi

# Make sure the monitoring script is executable
chmod +x ./Scripts/server_monitor.sh

# Check current server status
./Scripts/server_monitor.sh status

# Start servers if they're not running
echo -e "\n${BLUE}Starting servers if needed...${NC}"
./Scripts/server_monitor.sh start

# Check if monitor daemon is already running
MONITOR_PID=$(pgrep -f "Scripts/server_monitor.sh daemon")

if [ -n "$MONITOR_PID" ]; then
  echo -e "\n${GREEN}✓ Server monitor daemon is already running (PID: $MONITOR_PID)${NC}"
else
  echo -e "\n${YELLOW}Starting server monitor daemon...${NC}"
  # Start the monitor daemon in the background
  ./Scripts/server_monitor.sh daemon > monitor.log 2>&1 &
  DAEMON_PID=$!
  sleep 2
  
  # Verify the daemon started successfully
  if kill -0 $DAEMON_PID 2>/dev/null; then
    echo -e "${GREEN}✓ Server monitor daemon started successfully (PID: $DAEMON_PID)${NC}"
    echo -e "${GREEN}  The daemon will automatically restart your server if it crashes${NC}"
  else
    echo -e "${RED}✗ Failed to start server monitor daemon${NC}"
  fi
fi

echo -e "\n${GREEN}=== WhimsicalFrog Website is Ready! ===${NC}"
echo -e "${GREEN}You can now access your website at:${NC} http://localhost:8000"
echo -e "\n${BLUE}Active Monitoring:${NC}"
echo -e "  • Server monitor daemon is running in the background"
echo -e "  • Your server will automatically restart if it crashes"
echo -e "  • Monitor logs are saved to monitor.log"
echo -e "\n${YELLOW}To stop the servers and monitor, run:${NC}"
echo -e "  ./Scripts/server_monitor.sh stop && pkill -f 'Scripts/server_monitor.sh daemon'"
echo -e "\n${YELLOW}To restart the servers, run:${NC}"
echo -e "  ./Scripts/server_monitor.sh restart"
echo -e "\n${YELLOW}To check monitor status, run:${NC}"
echo -e "  ps aux | grep 'Scripts/server_monitor.sh daemon' | grep -v grep"

# Auto-close the terminal window after 15 seconds if double-clicked
if [ -t 0 ]; then
  echo -e "\n${BLUE}This window will close automatically in 15 seconds...${NC}"
  sleep 15
fi
