#!/usr/bin/env bash
# Wrapper to run the orchestrator from repo root
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
exec "$SCRIPT_DIR/scripts/release.sh" "$@"
