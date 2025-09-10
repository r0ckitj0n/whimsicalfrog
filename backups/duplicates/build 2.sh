#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")" && pwd)"

# Load NVM if available and select the appropriate Node version
if [ -s "$HOME/.nvm/nvm.sh" ]; then
  . "$HOME/.nvm/nvm.sh"
  if [ -f "$REPO_ROOT/.nvmrc" ]; then
    nvm install
    nvm use
  else
    # Fallback to LTS if no .nvmrc
    nvm install --lts || true
    nvm use --lts || true
  fi
fi

echo "Node: $(node -v 2>/dev/null || echo 'not found')"
echo "NPM:  $(npm -v 2>/dev/null || echo 'not found')"

# Frontend install/build
if [ -f "$REPO_ROOT/package.json" ]; then
  if command -v npm >/dev/null 2>&1; then
    if [ -f "$REPO_ROOT/package-lock.json" ]; then
      npm ci || npm install
    else
      npm install
    fi
    # Build if script exists
    if grep -q '"build"' "$REPO_ROOT/package.json"; then
      npm run build
    fi
  else
    echo "WARNING: npm not found; skipping frontend build"
  fi
fi

# PHP/composer install (optional)
if [ -f "$REPO_ROOT/composer.json" ]; then
  if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader || true
  else
    echo "INFO: composer not found; skipping composer install"
  fi
fi

echo "Build completed successfully." 
