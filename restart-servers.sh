#!/bin/bash
# Restart all development servers (PHP on port 8000 and Vite on port 5174)

SCRIPT_DIR=$(dirname "$0")
PROJECT_ROOT="$SCRIPT_DIR"

# 1. Stop any running dev servers
echo "Stopping existing dev servers..."
"$SCRIPT_DIR/stop-servers.sh"

# 2. Extra safety: kill any lingering Vite process that may still be holding 5174
if lsof -ti :5174 > /dev/null; then
  VITE_PID=$(lsof -ti :5174)
  echo "Force killing residual Vite server (PID: $VITE_PID)..."
  kill -9 "$VITE_PID" 2>/dev/null || true
fi

# 3. Remove any stale Vite \`hot\` file so that the next run generates a fresh one
HOT_FILE="$PROJECT_ROOT/hot"
if [ -f "$HOT_FILE" ]; then
  echo "Removing stale Vite hot file..."
  rm -f "$HOT_FILE"
fi

# 4. Start servers again
echo -e "\nStarting dev servers..."
"$SCRIPT_DIR/start-servers.sh"

echo -e "\nServer restart process complete."
