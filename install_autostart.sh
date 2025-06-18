#!/bin/bash

# WhimsicalFrog Auto-Start Installation Script
# This script sets up automatic startup of WhimsicalFrog servers when you log in

echo "🐸 WhimsicalFrog Auto-Start Installation"
echo "========================================"

# Make sure start_servers.sh is executable
chmod +x start_servers.sh
echo "✅ Made start_servers.sh executable"

# Copy the LaunchAgent plist to the correct location
cp com.whimsicalfrog.autostart.plist ~/Library/LaunchAgents/
echo "✅ Copied LaunchAgent configuration"

# Load the LaunchAgent
launchctl load ~/Library/LaunchAgents/com.whimsicalfrog.autostart.plist
echo "✅ Loaded LaunchAgent"

# Verify it's loaded
if launchctl list | grep -q "com.whimsicalfrog.autostart"; then
    echo "✅ Auto-start is now active!"
    echo ""
    echo "🎉 WhimsicalFrog will now start automatically when you log in!"
    echo ""
    echo "📝 What happens next:"
    echo "   • When you log in, WhimsicalFrog servers will start automatically"
    echo "   • The startup window will close automatically after 15 seconds"
    echo "   • You can access your site at http://localhost:8000"
    echo ""
    echo "🔧 Management commands:"
    echo "   • To disable auto-start: launchctl unload ~/Library/LaunchAgents/com.whimsicalfrog.autostart.plist"
    echo "   • To re-enable auto-start: launchctl load ~/Library/LaunchAgents/com.whimsicalfrog.autostart.plist"
    echo "   • To check status: launchctl list | grep whimsicalfrog"
    echo ""
    echo "📋 Logs are saved to: autostart.log"
else
    echo "❌ Failed to load LaunchAgent. Please check the configuration."
fi 