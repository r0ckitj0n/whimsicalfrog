#!/bin/bash

# WhimsicalFrog Website Starter
# This script starts the WhimsicalFrog website servers

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
if [ ! -f "./server_monitor.sh" ]; then
  echo -e "${RED}Error: server_monitor.sh not found!${NC}"
  echo -e "${YELLOW}Please make sure you're running this script from the correct directory.${NC}"
  exit 1
fi

# Make sure the monitoring script is executable
chmod +x ./server_monitor.sh

# Check current server status
./server_monitor.sh status

# Start servers if they're not running
echo -e "\n${BLUE}Starting servers if needed...${NC}"
./server_monitor.sh start

echo -e "\n${GREEN}=== WhimsicalFrog Website is Ready! ===${NC}"
echo -e "${GREEN}You can now access your website at:${NC} http://localhost:8000"
echo -e "\n${YELLOW}To stop the servers, run:${NC}"
echo -e "  ./server_monitor.sh stop"
echo -e "\n${YELLOW}To restart the servers, run:${NC}"
echo -e "  ./server_monitor.sh restart"
echo -e "\n${YELLOW}If the servers stop working, simply run this script again.${NC}"
echo -e "${YELLOW}For automatic monitoring, set up the cron job as shown above.${NC}"

# Auto-close the terminal window after 15 seconds if double-clicked
if [ -t 0 ]; then
  echo -e "\n${BLUE}This window will close automatically in 15 seconds...${NC}"
  sleep 15
fi
