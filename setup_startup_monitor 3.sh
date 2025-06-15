#!/bin/bash
#
# setup_startup_monitor.sh - Sets up Factory Bridge Monitor to start on login
#
# This script installs the Factory Bridge Monitor to automatically start when you
# log in to your Mac, ensuring Factory Bridge is always monitored and automatically
# restarted if it crashes.
#

# Color definitions for better readability
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[0;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
RESET='\033[0m'

# Print functions for better user feedback
print_success() { echo -e "${GREEN}✓ $1${RESET}"; }
print_error() { echo -e "${RED}✗ $1${RESET}"; }
print_warning() { echo -e "${YELLOW}! $1${RESET}"; }
print_info() { echo -e "${CYAN}ℹ $1${RESET}"; }
print_header() { echo -e "\n${BOLD}$1${RESET}\n"; }

# Configuration
HOME_DIR="$HOME"
MONITOR_SCRIPT="bridge_monitor.sh"
LAUNCH_AGENTS_DIR="$HOME/Library/LaunchAgents"
LOGS_DIR="$HOME/Library/Logs"
PLIST_FILE="$LAUNCH_AGENTS_DIR/com.whimsicalfrog.bridge-monitor.plist"

print_header "Factory Bridge Monitor Setup"
print_info "Setting up Factory Bridge Monitor to start automatically on login"

# Step 1: Check if bridge_monitor.sh exists in current directory
if [ ! -f "$MONITOR_SCRIPT" ]; then
    print_error "Monitor script not found: $MONITOR_SCRIPT"
    print_info "Please make sure bridge_monitor.sh is in the current directory"
    exit 1
fi

# Step 2: Create necessary directories
print_info "Creating required directories"
mkdir -p "$LAUNCH_AGENTS_DIR"
mkdir -p "$LOGS_DIR"

# Step 3: Copy the monitor script to home directory
print_info "Copying bridge_monitor.sh to home directory"
cp "$MONITOR_SCRIPT" "$HOME_DIR/"
if [ $? -eq 0 ]; then
    chmod +x "$HOME_DIR/$MONITOR_SCRIPT"
    print_success "Monitor script copied to $HOME_DIR/$MONITOR_SCRIPT"
else
    print_error "Failed to copy monitor script to home directory"
    exit 1
fi

# Step 4: Create the LaunchAgent plist file
print_info "Creating LaunchAgent plist file"
cat > "$PLIST_FILE" << EOL
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>com.whimsicalfrog.bridge-monitor</string>
    
    <key>ProgramArguments</key>
    <array>
        <string>/bin/bash</string>
        <string>-c</string>
        <string>$HOME_DIR/$MONITOR_SCRIPT monitor</string>
    </array>
    
    <key>RunAtLoad</key>
    <true/>
    
    <key>KeepAlive</key>
    <true/>
    
    <key>WorkingDirectory</key>
    <string>$HOME_DIR</string>
    
    <key>StandardErrorPath</key>
    <string>$LOGS_DIR/bridge_monitor_error.log</string>
    
    <key>StandardOutPath</key>
    <string>$LOGS_DIR/bridge_monitor_output.log</string>
    
    <key>EnvironmentVariables</key>
    <dict>
        <key>PATH</key>
        <string>/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin</string>
    </dict>
</dict>
</plist>
EOL

if [ $? -eq 0 ]; then
    print_success "LaunchAgent plist file created at $PLIST_FILE"
else
    print_error "Failed to create LaunchAgent plist file"
    exit 1
fi

# Step 5: Load the LaunchAgent
print_info "Loading LaunchAgent"
launchctl unload "$PLIST_FILE" 2>/dev/null  # Unload first in case it's already loaded
launchctl load -w "$PLIST_FILE"

if [ $? -eq 0 ]; then
    print_success "LaunchAgent loaded successfully"
else
    print_error "Failed to load LaunchAgent"
    print_info "You may need to run: launchctl load -w $PLIST_FILE"
    exit 1
fi

# Step 6: Verify it's working
print_info "Verifying LaunchAgent is running"
sleep 2  # Give it a moment to start

launchctl list | grep "com.whimsicalfrog.bridge-monitor" > /dev/null
if [ $? -eq 0 ]; then
    print_success "LaunchAgent is running correctly"
else
    print_warning "LaunchAgent doesn't appear to be running yet"
    print_info "You may need to log out and back in, or run: launchctl load -w $PLIST_FILE"
fi

print_header "Setup Complete!"
print_success "Factory Bridge Monitor has been installed and will start automatically on login"
print_info "Monitor log file: $LOGS_DIR/bridge_monitor.log"
print_info "LaunchAgent log files:"
print_info "  - $LOGS_DIR/bridge_monitor_output.log"
print_info "  - $LOGS_DIR/bridge_monitor_error.log"
print_success "Factory Bridge will now be monitored and automatically restarted if it crashes!"

# Show how to control or remove
print_header "Usage Information"
print_info "To check status: $HOME_DIR/$MONITOR_SCRIPT status"
print_info "To stop auto-startup: launchctl unload $PLIST_FILE"
print_info "To start again: launchctl load -w $PLIST_FILE"
print_info "To remove completely: launchctl unload $PLIST_FILE && rm $PLIST_FILE"
