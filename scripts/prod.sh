#!/bin/bash
set -euo pipefail

# Compatibility wrapper for legacy prod.sh usage.
# Routes to the new unified commit-time sync script.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

echo "[prod.sh] Legacy wrapper: routing to scripts/commit_mode_sync.sh"
exec "$PROJECT_ROOT/scripts/commit_mode_sync.sh"
