#!/bin/bash
# Start all development servers (PHP and Vite)

echo "Starting PHP server on port 8000 with router.php..."
php -S localhost:8000 router.php &
PHP_PID=$!
echo "PHP server started with PID: $PHP_PID"

echo "Starting Vite dev server on port 5174..."
npm run dev &
VITE_PID=$!
echo "Vite dev server started with PID: $VITE_PID"

echo "All servers are starting."
