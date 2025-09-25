#!/bin/bash
# WhimsicalFrog Hotfix (Minimal): Install the most permissive/safe .htaccess for Ionos
# Removes advanced directives that can cause 500s on certain Apache configs.
# Usage: bash scripts/hotfix_htaccess_minimal.sh
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

echo "[Hotfix-Minimal] Writing minimal .htaccess..."

TS=$(date +%Y%m%d_%H%M%S)
if [ -f .htaccess ]; then
  cp .htaccess ".htaccess.preminimal.$TS"
  echo "[Hotfix-Minimal] Backed up existing .htaccess -> .htaccess.preminimal.$TS"
fi

# Minimal, host-safe .htaccess: only basic rewrite rules
cat > .htaccess <<'EOF'
# Minimal .htaccess for Ionos
# NOTE: Intentionally avoids Options, Header, Expires, and FilesMatch blocks
# to prevent 500 errors from unsupported modules/AllowOverride policies.

RewriteEngine On
RewriteBase /

# Serve real files and directories directly (incl. PHP)
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d [OR]
RewriteCond %{REQUEST_FILENAME} \\.(php)$ -f
RewriteRule ^ - [L]

# Short-circuit /dist/* requests: serve if file exists
RewriteCond %{REQUEST_URI} ^/dist/ [NC]
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]

# Route everything else through router.php
RewriteRule ^ router.php [L,QSA]
EOF

# Preview
echo "[Hotfix-Minimal] .htaccess preview:"; cat .htaccess

# Deploy
if [ -x scripts/deploy.sh ]; then
  echo "[Hotfix-Minimal] Deploying..."
  bash scripts/deploy.sh || true
else
  echo "[Hotfix-Minimal] WARNING: scripts/deploy.sh missing or not executable."
fi

# Post hints
echo "[Hotfix-Minimal] Done. Test: / , /shop , /dist/.vite/manifest.json , a CSS asset."
