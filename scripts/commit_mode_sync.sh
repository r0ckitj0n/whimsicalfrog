#!/bin/bash
set -euo pipefail

# commit_mode_sync.sh
# Commit-time environment sync for WhimsicalFrog.
# - Detects dev/prod mode from current repo state.
# - Runs only required work based on staged files.
# - Restarts local servers only in prod mode when runtime changes require it.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT_DIR"

PORT=${PORT:-8080}
VITE_PORT=${VITE_DEV_PORT:-5176}
DRY_RUN=${WF_COMMIT_SYNC_DRY_RUN:-0}

mkdir -p logs

# Collect staged files once for deterministic decisions (portable for Bash 3.x on macOS).
STAGED_FILES=()
while IFS= read -r line; do
  STAGED_FILES+=("$line")
done < <(git diff --cached --name-only --diff-filter=ACMR)

if [ "${#STAGED_FILES[@]}" -eq 0 ]; then
  echo "[commit-sync] No staged file changes detected; skipping mode sync tasks."
  exit 0
fi

staged_joined() {
  printf '%s\n' "${STAGED_FILES[@]}"
}

matches_staged() {
  local pattern="$1"
  staged_joined | grep -Eq "$pattern"
}

# Determine current mode (favor explicit env, then repo flag).
MODE="dev"
if [ "${WF_VITE_MODE:-}" = "prod" ] || [ "${WF_VITE_DISABLE_DEV:-}" = "1" ] || [ -f ".disable-vite-dev" ]; then
  MODE="prod"
fi

# Build-relevant files are reported for visibility; prod mode still builds on every commit to mirror prod.sh.
BUILD_PATTERN='^(src/|index\.html$|vite\.config\.(ts|js|mjs|cjs)$|tailwind\.config\.(ts|js|mjs|cjs)$|postcss\.config\.(ts|js|mjs|cjs)$|tsconfig(\..+)?\.json$|package(-lock)?\.json$|images/|dist/)'

# Runtime restart is required when PHP/runtime/server control files changed.
RESTART_PATTERN='^(api/|includes/|config/|router\.php$|index\.php$|\.htaccess$|composer\.(json|lock)$|vendor/|pm2\.config\.cjs$|scripts/(start_servers|stop_servers|restart_servers|server_monitor)\.sh$|\.env(\..+)?$)'

BUILD_REQUIRED=0
RESTART_REQUIRED=0

if matches_staged "$BUILD_PATTERN"; then
  BUILD_REQUIRED=1
fi

if matches_staged "$RESTART_PATTERN"; then
  RESTART_REQUIRED=1
fi

echo "[commit-sync] Mode: $MODE"

echo "[commit-sync] Staged files: ${#STAGED_FILES[@]}"
if [ "$DRY_RUN" = "1" ]; then
  echo "[commit-sync] Dry run enabled; commands will be logged but not executed."
fi

if [ "$MODE" = "prod" ]; then
  # Keep production flags aligned with prod.sh behavior.
  if [ "$DRY_RUN" = "1" ]; then
    echo "[commit-sync] Would remove hot and ensure .disable-vite-dev exists."
  else
    rm -f hot || true
    touch .disable-vite-dev
  fi
  export WF_VITE_DISABLE_DEV=1
  export WF_VITE_MODE=prod

  if [ "$BUILD_REQUIRED" -eq 1 ]; then
    echo "[commit-sync] Build-relevant changes detected."
  else
    echo "[commit-sync] No build-relevant staged changes; building anyway to mirror prod.sh."
  fi

  if [ ! -d "node_modules" ] && [ "$DRY_RUN" != "1" ]; then
    echo "[commit-sync] Installing npm dependencies..."
    npm install --silent
  fi

  if [ "$DRY_RUN" = "1" ]; then
    echo "[commit-sync] Would run: npm run build"
  else
    echo "[commit-sync] Building production assets..."
    npm run build
  fi

  if [ "$RESTART_REQUIRED" -eq 1 ]; then
    if [ "$DRY_RUN" = "1" ]; then
      echo "[commit-sync] Would run: ./scripts/restart_servers.sh"
    else
      echo "[commit-sync] Runtime changes detected; restarting local servers..."
      ./scripts/restart_servers.sh
    fi
  else
    echo "[commit-sync] No runtime-impacting staged changes; skipping server restart."
  fi
else
  # Keep development mode aligned with dev.sh behavior but avoid unnecessary restarts.
  if [ "$DRY_RUN" = "1" ]; then
    echo "[commit-sync] Would remove .disable-vite-dev for dev mode."
  else
    rm -f .disable-vite-dev || true
  fi
  : "${WF_VITE_ORIGIN:=http://localhost:${VITE_PORT}}"
  export WF_VITE_ORIGIN

  echo "[commit-sync] Dev mode active; skipping production build and server restart."
fi
