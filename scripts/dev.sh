#!/bin/bash
set -euo pipefail

# Compatibility wrapper for legacy dev.sh usage.
# Routes to the new unified commit-time sync script.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

echo "[dev.sh] Legacy wrapper: routing to scripts/commit_mode_sync.sh"
exec "$PROJECT_ROOT/scripts/commit_mode_sync.sh"
