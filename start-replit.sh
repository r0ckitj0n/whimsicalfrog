#!/bin/bash

# Start script for WhimsicalFrog in Replit environment

set -e

# Create logs directory
mkdir -p logs

# Start PHP server on localhost:8080 in background
echo "Starting PHP backend server on localhost:8080..."
php -S localhost:8080 -t . router.php > logs/php_server.log 2>&1 &

# Wait a bit for PHP server to start
sleep 2

# Start Vite dev server on 0.0.0.0:5000 (foreground)
echo "Starting Vite frontend server on 0.0.0.0:5000..."
npm run dev