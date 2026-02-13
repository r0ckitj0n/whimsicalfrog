#!/bin/bash

# Code-only deployment (preserves images/** and skips DB changes)
# Wrapper for scripts/deploy.sh --code-only

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
exec bash "$SCRIPT_DIR/deploy.sh" --code-only "$@"

