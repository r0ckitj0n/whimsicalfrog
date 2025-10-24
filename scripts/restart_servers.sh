#!/bin/bash

# restart_servers.sh - Restart WhimsicalFrog development server
# Usage: ./restart_servers.sh
# Make sure the script is executable:  chmod +x restart_servers.sh

set -e

# Change to project root (one level up from scripts directory)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/.."
# Ensure logs directory exists for server logs
mkdir -p logs

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}WhimsicalFrog Server Restart${NC}"

########################################
# Stop any existing servers
########################################
########################################
echo -e "${YELLOW}Stopping any existing PHP and Vite servers...${NC}"
# Prefer managed stop via PM2 for Vite
if command -v npx >/dev/null 2>&1; then
  npx pm2 stop wf-vite 2>/dev/null || true
fi
# Also kill any stray vite listeners as belt-and-suspenders
pkill -f "vite" 2>/dev/null || true
pkill -f "npm run dev" 2>/dev/null || true
# Wait for ports to be released
sleep 1

# Kill all PHP dev servers
pkill -f "php -S localhost:" 2>/dev/null || true
PORT=8080
# Also kill any process bound to the PHP port directly
lsof -ti tcp:$PORT | xargs kill -9 2>/dev/null || true

# Wait for ports to be released
sleep 2

########################################
# Start PHP server on port $PORT
########################################
VITE_PORT=5176
echo -e "${GREEN}Starting PHP dev server on http://localhost:$PORT${NC}"

# Start server in background using router.php so we can proxy Vite dev paths
# Ensure the PHP port is free (belt-and-suspenders)
lsof -ti tcp:$PORT | xargs kill -9 2>/dev/null || true
php -S localhost:$PORT -t . router.php > logs/php_server.log 2>&1 &
PHP_PID=$!

# Wait a moment and check if it started successfully
sleep 2
if kill -0 $PHP_PID 2>/dev/null; then
  echo -e "${GREEN}✓ PHP server started successfully (PID $PHP_PID)${NC}"
  echo -e "${GREEN}✓ Website available at: http://localhost:$PORT${NC}"
else
  echo -e "${RED}✗ Failed to start PHP server${NC}"
  echo -e "${RED}Check logs/php_server.log for details${NC}"
  exit 1
fi

########################################
# Start Vite dev server with PM2
########################################

echo -e "${GREEN}Starting Vite dev server via PM2 on http://localhost:$VITE_PORT${NC}"
# Ensure logs directory exists
mkdir -p logs
# Ensure node_modules are installed (skip if present)
if [ ! -d "node_modules" ]; then
  echo -e "${YELLOW}node_modules not found – installing dependencies (this may take a while)...${NC}"
  npm ci --include=optional --silent || npm install --include=optional --silent
fi
# Free desired port just in case
lsof -ti tcp:$VITE_PORT | xargs kill -9 2>/dev/null || true
# Start/Restart pm2 app
npx pm2 start pm2.config.cjs >/dev/null 2>&1 || true
npx pm2 restart wf-vite >/dev/null 2>&1 || npx pm2 start pm2.config.cjs >/dev/null 2>&1 || true
npx pm2 save >/dev/null 2>&1 || true
# Probe @vite/client up to ~6s
TRIES=0
until curl -sI "http://localhost:$VITE_PORT/@vite/client" | grep -q " 200" || [ $TRIES -gt 12 ]; do
  sleep 0.5
  TRIES=$((TRIES+1))
done
if curl -sI "http://localhost:$VITE_PORT/@vite/client" | grep -q " 200"; then
  echo -e "${GREEN}✓ Vite dev server is responding on http://localhost:$VITE_PORT${NC}"
else
  echo -e "${RED}✗ Vite dev server did not respond on http://localhost:$VITE_PORT. See logs/vite_server.log or 'npx pm2 logs wf-vite'${NC}"
fi

########################################
# Ensure MySQL is running
########################################
echo -e "${YELLOW}Checking MySQL status...${NC}"

# Try to detect the site's current DB settings via PHP so we match what the app uses
if command -v php >/dev/null 2>&1; then
  if [ -f "api/config.php" ]; then
    DETECT_OUT=$(php -r '
      require_once "api/config.php";
      if (function_exists("wf_get_db_config")) {
        $cfg = wf_get_db_config("current");
        foreach (["host","db","user","pass","port","socket"] as $k) {
          $v = isset($cfg[$k]) ? (string)$cfg[$k] : "";
          // Basic escaping for shell eval: escape backslash and double quote
          $v = str_replace(["\\", "\""], ["\\\\", "\\\""], $v);
          echo "WF_DB_DETECTED_" . strtoupper($k) . "=\"$v\"\n";
        }
      }
    ' 2>/dev/null || true)
    # shellcheck disable=SC2046
    eval $(printf '%s' "$DETECT_OUT")
    # Apply detected values as defaults if user/env not already set
    [ -n "$WF_DB_DETECTED_HOST" ]   && : "${WF_DB_LOCAL_HOST:=$WF_DB_DETECTED_HOST}"
    [ -n "$WF_DB_DETECTED_DB" ]     && : "${WF_DB_LOCAL_NAME:=$WF_DB_DETECTED_DB}"
    [ -n "$WF_DB_DETECTED_USER" ]   && : "${WF_DB_LOCAL_USER:=$WF_DB_DETECTED_USER}"
    [ -n "$WF_DB_DETECTED_PASS" ]   && : "${WF_DB_LOCAL_PASS:=$WF_DB_DETECTED_PASS}"
    [ -n "$WF_DB_DETECTED_PORT" ]   && : "${WF_DB_LOCAL_PORT:=$WF_DB_DETECTED_PORT}"
    [ -n "$WF_DB_DETECTED_SOCKET" ] && : "${WF_DB_LOCAL_SOCKET:=$WF_DB_DETECTED_SOCKET}"
    if [ -n "$WF_DB_DETECTED_SOCKET" ]; then
      echo -e "${GREEN}Detected DB socket from PHP config:${NC} $WF_DB_DETECTED_SOCKET"
    else
      echo -e "${GREEN}Detected DB host/port from PHP config:${NC} ${WF_DB_LOCAL_HOST:-localhost}:${WF_DB_LOCAL_PORT:-3306}"
    fi
  fi
fi
## Defaults for local DB and wait timing (override via env)
: "${WF_DB_LOCAL_HOST:=localhost}"
: "${WF_DB_LOCAL_USER:=root}"
: "${WF_DB_LOCAL_PASS:=Palz2516!}"
: "${WF_DB_LOCAL_PORT:=3306}"
: "${WF_DB_LOCAL_SOCKET:=}"
: "${WF_MYSQL_WAIT_SECONDS:=20}"

# If the site is configured to use a remote DB host (not localhost) and no socket,
# do not attempt to manage a local MySQL instance.
case "${WF_DB_LOCAL_HOST}" in
  localhost|127.0.0.1|"" ) USES_REMOTE_DB=0 ;;
  * ) USES_REMOTE_DB=1 ;;
esac
if [ -n "$WF_DB_LOCAL_SOCKET" ]; then USES_REMOTE_DB=0; fi

if [ "$USES_REMOTE_DB" = "1" ]; then
  echo -e "${YELLOW}Detected remote DB host from app config:${NC} ${WF_DB_LOCAL_HOST}:${WF_DB_LOCAL_PORT}"
  echo -e "${GREEN}Skipping local MySQL start – site will use the remote database.${NC}"
  # Exit MySQL section early
  echo -e "\n${GREEN}=== WhimsicalFrog Server Restart Complete ===${NC}"
  echo -e "${GREEN}Your website is now running at: http://localhost:$PORT${NC}"
  echo -e "${YELLOW}To stop the server: pkill -f 'php -S localhost:$PORT'${NC}"
  exit 0
fi

# Helper: wait for MySQL readiness using mysqladmin ping if available, else port check
wait_for_mysql_ready() {
  local waited=0
  local max_wait=$WF_MYSQL_WAIT_SECONDS
  while [ $waited -lt $max_wait ]; do
    if command -v mysqladmin >/dev/null 2>&1; then
      # 0) Try bare ping (often succeeds without creds when socket is active)
      if mysqladmin ping --silent >/dev/null 2>&1; then
        return 0
      fi
      # 1) Try socket path if provided or common defaults
      if [ -n "$WF_DB_LOCAL_SOCKET" ] && mysqladmin --socket="$WF_DB_LOCAL_SOCKET" ping --silent >/dev/null 2>&1; then
        return 0
      fi
      for sock in \
        /tmp/mysql.sock \
        /usr/local/var/mysql/mysql.sock \
        /opt/homebrew/var/mysql/mysql.sock \
        /Applications/MAMP/tmp/mysql/mysql.sock; do
        if [ -S "$sock" ] && mysqladmin --socket="$sock" ping --silent >/dev/null 2>&1; then
          return 0
        fi
      done
      # 2) Try TCP ping using provided host/port and optional creds
      if mysqladmin --host="$WF_DB_LOCAL_HOST" --port="$WF_DB_LOCAL_PORT" ping --silent >/dev/null 2>&1; then
        return 0
      fi
      if mysqladmin --host="$WF_DB_LOCAL_HOST" --port="$WF_DB_LOCAL_PORT" \
         --user="$WF_DB_LOCAL_USER" --password="$WF_DB_LOCAL_PASS" \
         ping --silent >/dev/null 2>&1; then
        return 0
      fi
    fi
    # Fallbacks when mysqladmin is not available
    # 1) Socket existence check (treat as ready if socket file exists)
    if [ -n "$WF_DB_LOCAL_SOCKET" ] && [ -S "$WF_DB_LOCAL_SOCKET" ]; then
      return 0
    fi
    for sock in \
      /tmp/mysql.sock \
      /usr/local/var/mysql/mysql.sock \
      /opt/homebrew/var/mysql/mysql.sock \
      /Applications/MAMP/tmp/mysql/mysql.sock; do
      if [ -S "$sock" ]; then
        WF_DB_LOCAL_SOCKET="$sock"
        return 0
      fi
    done
    # 2) Port listen check
    if lsof -ti :"$WF_DB_LOCAL_PORT" >/dev/null 2>&1; then
      return 0
    fi
    sleep 1
    waited=$((waited+1))
  done
  return 1
}

# First, check if something is already listening on the configured MySQL port
if lsof -ti :"$WF_DB_LOCAL_PORT" >/dev/null 2>&1 || wait_for_mysql_ready; then
  echo -e "${GREEN}✓ MySQL is already running${NC}"
else
  echo -e "${YELLOW}Starting MySQL...${NC}"

  STARTED=false

  # 1) Try Homebrew service if available.
  if command -v brew >/dev/null 2>&1; then
    # Allow explicit override
    if [ -n "$WF_MYSQL_SERVICE" ]; then
      brew services start "$WF_MYSQL_SERVICE" > /dev/null 2>&1 && STARTED=true
    else
      # Start first matching MySQL/MariaDB formula if installed
      FORMULA=$(brew list --formula | grep -E '^(mysql(@[0-9]+(\.[0-9]+)*)?|mariadb)$' | head -n1)
      if [ -n "$FORMULA" ]; then
        brew services start "$FORMULA" > /dev/null 2>&1 && STARTED=true
      fi
    fi
  fi

  # 2) Fallback to native macOS installer paths if Homebrew method didn’t work.
  if ! $STARTED; then
    if [ -x "/usr/local/mysql/support-files/mysql.server" ]; then
      /usr/local/mysql/support-files/mysql.server start > /dev/null 2>&1 && STARTED=true
    elif [ -x "/usr/local/mysql/bin/mysql.server" ]; then
      /usr/local/mysql/bin/mysql.server start > /dev/null 2>&1 && STARTED=true
    fi
  fi

  # 3) After attempting a start (or even if no manager), wait for readiness anyway
  echo -e "${YELLOW}Waiting up to ${WF_MYSQL_WAIT_SECONDS}s for MySQL to become ready...${NC}"
  if wait_for_mysql_ready; then
    echo -e "${GREEN}✓ MySQL is up and responding (socket or ${WF_DB_LOCAL_HOST}:${WF_DB_LOCAL_PORT})${NC}"
  else
    if $STARTED; then
      echo -e "${RED}✗ MySQL did not become ready within ${WF_MYSQL_WAIT_SECONDS}s${NC}"
    else
      echo -e "${RED}✗ Failed to start MySQL (no known service manager found) and service not detected as ready${NC}"
    fi
  fi
fi

echo -e "\n${GREEN}=== WhimsicalFrog Server Restart Complete ===${NC}"
echo -e "${GREEN}Your website is now running at: http://localhost:$PORT${NC}"
echo -e "${YELLOW}To stop the server: pkill -f 'php -S localhost:$PORT'${NC}"