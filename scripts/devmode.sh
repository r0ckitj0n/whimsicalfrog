#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

log() {
  printf '[devmode.sh] %s\n' "$1"
}

log 'Re-enabling Vite dev mode (creating hot file, removing .disable-vite-dev)...'
# Default Vite dev origin matches package.json dev script ports
printf 'http://localhost:5176\n' > hot
rm -f .disable-vite-dev

log 'Dev mode is now enabled. Start the Vite dev server (npm run dev) if it is not already running.'
