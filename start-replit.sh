#!/bin/bash

# Start script for WhimsicalFrog in Replit environment

set -e

# Create logs directory
mkdir -p logs

echo "Starting WhimsicalFrog PHP application on 0.0.0.0:5000..."

# Start PHP server on 0.0.0.0:5000 (foreground) - this is the main application
php -S 0.0.0.0:5000 -t . router.php