#!/bin/bash
#
# bridge_monitor.sh - Factory Bridge Process Monitor
#
# This script monitors the Factory Bridge application and automatically
# restarts it if it crashes or stops running. It logs all activities with
# timestamps and can run in the background.
#
# Usage: ./bridge_monitor.sh [start|stop|status]
#

# =====================================================================
# Configuration Variables (modify as needed)
# =====================================================================

# Check interval in seconds
CHECK_INTERVAL=30

# Delay between restart attempts in seconds
RESTART_DELAY=5

# Maximum number of restart attempts before giving up
MAX_RESTART_ATTEMPTS=5

# Log file location
LOG_FILE="$HOME/Library/Logs/bridge_monitor.log"

# PID file to track this script when running in background
PID_FILE="$HOME/Library/Logs/bridge_monitor.pid"

# Common locations to search for the Factory Bridge app
APP_LOCATIONS=(
    "/Applications/Factory Bridge.app"
    "$HOME/Applications/Factory Bridge.app"
    "/Applications/Factory/Factory Bridge.app"
    "$HOME/Applications/Factory/Factory Bridge.app"
)

# =====================================================================
# Function Definitions
# =====================================================================

# Function to log messages with timestamps
log() {
    local timestamp=$(date "+%Y-%m-%d %H:%M:%S")
    echo "[$timestamp] $1" >> "$LOG_FILE"
    
    # If verbose mode is enabled, also print to console
    if [ "$VERBOSE" = true ]; then
        echo "[$timestamp] $1"
    fi
}

# Function to find the Factory Bridge application
find_bridge_app() {
    for location in "${APP_LOCATIONS[@]}"; do
        if [ -d "$location" ]; then
            echo "$location"
            return 0
        fi
    done
    
    # If we get here, we couldn't find the app
    return 1
}

# Function to check if Factory Bridge is running
is_bridge_running() {
    # Try different process names that might match Factory Bridge
    pgrep -f "Factory Bridge" > /dev/null || pgrep -f "FactoryBridge" > /dev/null
    return $?
}

# Function to start the Factory Bridge application
start_bridge() {
    local app_path=$(find_bridge_app)
    
    if [ -z "$app_path" ]; then
        log "ERROR: Could not find Factory Bridge application"
        return 1
    fi
    
    log "Starting Factory Bridge from: $app_path"
    open "$app_path"
    
    # Wait a moment for the app to launch
    sleep 3
    
    # Check if it's running
    if is_bridge_running; then
        log "Factory Bridge started successfully"
        return 0
    else
        log "Failed to start Factory Bridge"
        return 1
    fi
}

# Function to stop the Factory Bridge application
stop_bridge() {
    log "Stopping Factory Bridge"
    pkill -f "Factory Bridge" || pkill -f "FactoryBridge"
    
    # Wait a moment to ensure it's stopped
    sleep 2
    
    # Check if it's still running
    if ! is_bridge_running; then
        log "Factory Bridge stopped successfully"
        return 0
    else
        log "Failed to stop Factory Bridge, attempting force quit"
        pkill -9 -f "Factory Bridge" || pkill -9 -f "FactoryBridge"
        sleep 1
        return 0
    fi
}

# Function to restart the Factory Bridge application
restart_bridge() {
    log "Restarting Factory Bridge"
    stop_bridge
    sleep "$RESTART_DELAY"
    start_bridge
    return $?
}

# Function to start the monitor in the background
start_monitor_background() {
    if [ -f "$PID_FILE" ]; then
        local pid=$(cat "$PID_FILE")
        if ps -p "$pid" > /dev/null; then
            log "Bridge monitor is already running with PID $pid"
            return 1
        else
            log "Removing stale PID file"
            rm "$PID_FILE"
        fi
    fi
    
    # Start this script in the background
    nohup "$0" monitor > /dev/null 2>&1 &
    echo $! > "$PID_FILE"
    log "Bridge monitor started in background with PID $!"
    echo "Bridge monitor started in background. Check $LOG_FILE for logs."
}

# Function to stop the background monitor
stop_monitor() {
    if [ -f "$PID_FILE" ]; then
        local pid=$(cat "$PID_FILE")
        if ps -p "$pid" > /dev/null; then
            log "Stopping bridge monitor with PID $pid"
            kill "$pid"
            rm "$PID_FILE"
            echo "Bridge monitor stopped."
            return 0
        fi
    fi
    
    echo "Bridge monitor is not running."
    return 1
}

# Function to show monitor status
show_status() {
    if [ -f "$PID_FILE" ]; then
        local pid=$(cat "$PID_FILE")
        if ps -p "$pid" > /dev/null; then
            echo "Bridge monitor is running with PID $pid"
            echo "Log file: $LOG_FILE"
            
            if is_bridge_running; then
                echo "Factory Bridge is currently RUNNING"
            else
                echo "Factory Bridge is currently NOT RUNNING"
            fi
            return 0
        fi
    fi
    
    echo "Bridge monitor is not running."
    
    if is_bridge_running; then
        echo "Factory Bridge is currently RUNNING"
    else
        echo "Factory Bridge is currently NOT RUNNING"
    fi
}

# Main monitoring function
monitor_bridge() {
    log "Starting Factory Bridge monitoring"
    log "Check interval: $CHECK_INTERVAL seconds"
    log "Restart delay: $RESTART_DELAY seconds"
    
    # Ensure Bridge is running when we start monitoring
    if ! is_bridge_running; then
        log "Factory Bridge not running at monitor start, attempting to start it"
        start_bridge
    else
        log "Factory Bridge is already running"
    fi
    
    # Main monitoring loop
    while true; do
        if ! is_bridge_running; then
            log "Factory Bridge is not running, attempting to restart"
            
            local attempts=0
            local restart_success=false
            
            while [ $attempts -lt $MAX_RESTART_ATTEMPTS ]; do
                attempts=$((attempts + 1))
                log "Restart attempt $attempts of $MAX_RESTART_ATTEMPTS"
                
                if restart_bridge; then
                    restart_success=true
                    break
                else
                    log "Restart attempt $attempts failed, waiting $RESTART_DELAY seconds before trying again"
                    sleep "$RESTART_DELAY"
                fi
            done
            
            if [ "$restart_success" = true ]; then
                log "Factory Bridge restarted successfully after $attempts attempt(s)"
            else
                log "ERROR: Failed to restart Factory Bridge after $MAX_RESTART_ATTEMPTS attempts"
                log "Will continue monitoring and try again on next check"
            fi
        fi
        
        # Wait for next check interval
        sleep "$CHECK_INTERVAL"
    done
}

# =====================================================================
# Main Script Logic
# =====================================================================

# Create log directory if it doesn't exist
mkdir -p "$(dirname "$LOG_FILE")"

# Process command line arguments
case "$1" in
    start)
        start_monitor_background
        ;;
    stop)
        stop_monitor
        ;;
    status)
        show_status
        ;;
    monitor)
        # This is the actual monitoring mode, called by start_monitor_background
        VERBOSE=false
        monitor_bridge
        ;;
    *)
        # Interactive mode with console output
        VERBOSE=true
        echo "Factory Bridge Monitor"
        echo "----------------------"
        echo "Running in foreground mode. Press Ctrl+C to stop."
        echo "Log file: $LOG_FILE"
        echo ""
        monitor_bridge
        ;;
esac

exit 0
