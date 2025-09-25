#!/bin/bash
# Fix Vite dev env: clean installs and caches, prefer Node 18 LTS
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

bold() { printf "\033[1m%s\033[0m\n" "$*"; }
info() { printf "[fix-dev] %s\n" "$*"; }

bold "WhimsicalFrog: Fix Vite Dev Environment"

# 1) Detect Node version
NODE_VER=$(node -v 2>/dev/null || echo "unknown")
info "Node version: ${NODE_VER}"

# 2) Try to switch to Node 18 via nvm if available and not already v18
if command -v nvm >/dev/null 2>&1; then
  if ! node -v | grep -qE '^v18\.'; then
    info "Using nvm to switch to Node 18 (if installed)"
    # shellcheck disable=SC1090
    [ -s "$HOME/.nvm/nvm.sh" ] && . "$HOME/.nvm/nvm.sh"
    nvm install 18 || true
    nvm use 18 || true
    info "Now on: $(node -v || echo unknown)"
  fi
else
  info "nvm not found; continuing with current Node (${NODE_VER})."
  info "If issues persist, consider switching to Node 18 LTS."
fi

# 3) Clean caches and installs
info "Removing node_modules and lockfile..."
rm -rf node_modules package-lock.json || true

info "Installing dependencies (skip husky hooks)..."
HUSKY_SKIP_INSTALL=true npm install

# 4) Clear Vite caches
info "Clearing Vite caches..."
rm -rf node_modules/.vite .vite .cache || true

# 5) Print Vite version
VITE_VER=$(npx vite --version 2>/dev/null || echo "vite not found")
info "Vite version: ${VITE_VER}"

bold "Done. Now run: npm run dev"
