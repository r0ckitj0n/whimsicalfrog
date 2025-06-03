#!/bin/bash
#
# bridge_launcher.sh - User-friendly launcher for Factory Bridge Monitor
#
# This script provides a simple interface to start, stop, and check the status
# of the Factory Bridge monitor script.
#
# Usage: ./bridge_launcher.sh [start|stop|status|help]
#

# =====================================================================
# Configuration
# =====================================================================

# Path to the monitor script
MONITOR_SCRIPT="$HOME/bridge_monitor.sh"

# Log file location (same as in monitor script)
LOG_FILE="$HOME/Library/Logs/bridge_monitor.log"

# =====================================================================
# Color definitions for better readability
# =====================================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
BOLD='\033[1m'
RESET='\033[0m'

# =====================================================================
# Helper Functions
# =====================================================================

# Function to print section headers
print_header() {
    echo -e "\n${BOLD}${BLUE}$1${RESET}\n"
}

# Function to print success messages
print_success() {
    echo -e "${GREEN}✓ $1${RESET}"
}

# Function to print error messages
print_error() {
    echo -e "${RED}✗ $1${RESET}"
}

# Function to print warning messages
print_warning() {
    echo -e "${YELLOW}! $1${RESET}"
}

# Function to print info messages
print_info() {
    echo -e "${CYAN}ℹ $1${RESET}"
}

# Function to check if the monitor script exists
check_monitor_script() {
    if [ ! -f "$MONITOR_SCRIPT" ]; then
        print_error "Monitor script not found at: $MONITOR_SCRIPT"
        print_info "Please make sure bridge_monitor.sh is in your home directory."
        exit 1
    fi
    
    if [ ! -x "$MONITOR_SCRIPT" ]; then
        print_warning "Monitor script is not executable. Fixing permissions..."
        chmod +x "$MONITOR_SCRIPT"
        if [ $? -eq 0 ]; then
            print_success "Permissions fixed!"
        else
            print_error "Failed to make the script executable."
            exit 1
        fi
    fi
}

# Function to show usage information
show_usage() {
    print_header "Factory Bridge Launcher"
    echo -e "A user-friendly tool to manage the Factory Bridge monitor."
    echo
    echo -e "${BOLD}Usage:${RESET}"
    echo -e "  ./bridge_launcher.sh ${CYAN}command${RESET}"
    echo
    echo -e "${BOLD}Commands:${RESET}"
    echo -e "  ${CYAN}start${RESET}     Start the Factory Bridge monitor in the background"
    echo -e "  ${CYAN}stop${RESET}      Stop the running monitor"
    echo -e "  ${CYAN}status${RESET}    Check if the monitor is running and show Bridge status"
    echo -e "  ${CYAN}restart${RESET}   Restart the monitor"
    echo -e "  ${CYAN}log${RESET}       Show the last 20 lines of the monitor log"
    echo -e "  ${CYAN}help${RESET}      Show this help message"
    echo
    echo -e "${BOLD}Examples:${RESET}"
    echo -e "  ./bridge_launcher.sh ${CYAN}start${RESET}    # Start the monitor in the background"
    echo -e "  ./bridge_launcher.sh ${CYAN}status${RESET}   # Check if everything is running"
    echo
}

# Function to show the log
show_log() {
    if [ -f "$LOG_FILE" ]; then
        print_header "Last 20 log entries"
        tail -n 20 "$LOG_FILE"
    else
        print_error "Log file not found at: $LOG_FILE"
    fi
}

# =====================================================================
# Main Script Logic
# =====================================================================

# Show usage if no arguments provided
if [ $# -eq 0 ]; then
    show_usage
    exit 0
fi

# Process command line arguments
case "$1" in
    start)
        print_header "Starting Factory Bridge Monitor"
        check_monitor_script
        "$MONITOR_SCRIPT" start
        if [ $? -eq 0 ]; then
            print_success "Monitor started successfully!"
            print_info "Run './bridge_launcher.sh status' to check status"
            print_info "Run './bridge_launcher.sh log' to view the log"
        fi
        ;;
    stop)
        print_header "Stopping Factory Bridge Monitor"
        check_monitor_script
        "$MONITOR_SCRIPT" stop
        if [ $? -eq 0 ]; then
            print_success "Monitor stopped successfully!"
        fi
        ;;
    restart)
        print_header "Restarting Factory Bridge Monitor"
        check_monitor_script
        "$MONITOR_SCRIPT" stop
        sleep 2
        "$MONITOR_SCRIPT" start
        if [ $? -eq 0 ]; then
            print_success "Monitor restarted successfully!"
            print_info "Run './bridge_launcher.sh status' to check status"
        fi
        ;;
    status)
        print_header "Factory Bridge Status"
        check_monitor_script
        "$MONITOR_SCRIPT" status
        ;;
    log)
        check_monitor_script
        show_log
        ;;
    help)
        show_usage
        ;;
    *)
        print_error "Unknown command: $1"
        show_usage
        exit 1
        ;;
esac

exit 0
