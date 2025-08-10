#!/bin/bash
# Stop all running development servers (PHP and Vite)

# Stop PHP server on port 8888
PHP_PID=$(lsof -ti :8000)
if [ -n "$PHP_PID" ]; then
    echo "Stopping PHP server (PID: $PHP_PID)..."
    kill $PHP_PID
else
    echo "PHP server not running."
fi

# Stop Vite server on port 5173
VITE_PID=$(lsof -ti :5174)
if [ -n "$VITE_PID" ]; then
    echo "Stopping Vite dev server (PID: $VITE_PID)..."
    kill $VITE_PID
else
    echo "Vite dev server not running."
fi

echo "All servers stopped."
