#!/bin/bash
# WhimsicalFrog Hotfix: Add minimal .htaccess in dist/ to ensure static assets are served
# Some hosts may apply parent rewrites to child dirs; this disables rewrites inside dist/
# Usage: bash scripts/hotfix_dist_htaccess.sh
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

mkdir -p dist

TS=$(date +%Y%m%d_%H%M%S)
if [ -f dist/.htaccess ]; then
  cp dist/.htaccess "dist/.htaccess.backup.$TS"
  echo "[Hotfix-dist] Backed up existing dist/.htaccess -> dist/.htaccess.backup.$TS"
fi

cat > dist/.htaccess <<'EOF'
# Minimal .htaccess inside /dist to ensure static assets are served directly
# Disable mod_rewrite here
RewriteEngine Off

# Optional: ensure correct content types (commented if host disallows AddType)
#<IfModule mod_mime.c>
#  AddType application/javascript .js
#  AddType text/css .css
#  AddType application/json .json
#  AddType image/webp .webp
#  AddType image/svg+xml .svg
#</IfModule>
EOF

echo "[Hotfix-dist] Wrote dist/.htaccess:"; cat dist/.htaccess

if [ -x scripts/deploy.sh ]; then
  echo "[Hotfix-dist] Deploying..."
  bash scripts/deploy.sh || true
else
  echo "[Hotfix-dist] WARNING: scripts/deploy.sh missing or not executable."
fi

echo "[Hotfix-dist] Done. Test: /dist/.vite/manifest.json and a CSS/JS asset."
