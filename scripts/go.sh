#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

log() {
  printf '[go.sh] %s\n' "$1"
}

usage() {
  cat <<'EOT'
Usage: scripts/go.sh [--deploy|--live] [--no-deploy]

Defaults to local-only mode (no live deployment). Pass --deploy or --live to
upload dist/ via scripts/deploy_dist.sh. Explicit --no-deploy forces local mode
even if WF_GO_DEPLOY=1 is exported.
EOT
}

DO_DEPLOY=${WF_GO_DEPLOY:-0}
while (($#)); do
  case "$1" in
    --deploy|--live)
      DO_DEPLOY=1
      ;;
    --no-deploy)
      DO_DEPLOY=0
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "[go.sh] Unknown argument: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
  shift
done

# Note: Now using .cursorrules with Antigravity (Windsurf sync removed)

log 'Disabling Vite dev mode (removing hot file, setting .disable-vite-dev)...'
rm -f hot
touch .disable-vite-dev

log 'Clearing caches...'
./scripts/clear_caches.sh

# Ensure dist is rebuilt fresh so new bundles (e.g., admin-inventory) are emitted
log 'Removing previous dist output...'
rm -rf dist

log 'Running npm run build...'
npm run build

# Optional: show the emitted admin-inventory bundle hash to confirm freshness
if [ -f dist/.vite/manifest.json ]; then
  log "Built bundles:"
  grep -n "admin-inventory" dist/.vite/manifest.json || true
fi

if [ "$DO_DEPLOY" = "1" ]; then
  log 'Running scripts/deploy_dist.sh (live deploy requested)...'
  ./scripts/deploy_dist.sh
else
  log 'Skipping scripts/deploy_dist.sh (local test mode). Use --deploy to send dist/ to live.'
fi

log 'Running scripts/restart_servers.sh...'
./scripts/restart_servers.sh

log 'All steps completed.'
