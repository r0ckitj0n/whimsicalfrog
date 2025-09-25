#!/bin/bash
# WhimsicalFrog Hotfix: Restore known-good .htaccess and clear bad artifacts
# Usage: bash scripts/hotfix_htaccess.sh
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

echo "[Hotfix] Starting .htaccess restoration and verification..."

# 1) Source of truth: backup from 2025-09-23
BACKUP_HTACCESS_PATH="/Users/jongraves/Documents/Websites/WhimsicalFrog - Backups/2025-09-23/.htaccess"
if [ ! -f "$BACKUP_HTACCESS_PATH" ]; then
  echo "[Hotfix] ERROR: Backup .htaccess not found at: $BACKUP_HTACCESS_PATH"
  exit 1
fi

# 2) Save current .htaccess aside if present
TS=$(date +%Y%m%d_%H%M%S)
if [ -f .htaccess ]; then
  cp .htaccess ".htaccess.hotfix_backup.$TS"
  echo "[Hotfix] Backed up existing .htaccess -> .htaccess.hotfix_backup.$TS"
fi

# 3) Restore the known-good .htaccess
cp "$BACKUP_HTACCESS_PATH" .htaccess

# 4) Ensure PHP files and directories are allowed before routing to router.php
#    This is a defensive addition; it keeps the original rules intact but
#    guarantees PHP files and real directories are served directly.
if ! grep -q "Allow direct access to existing files, directories, and PHP files" .htaccess; then
  cat >> .htaccess <<'EOF'

# Allow direct access to existing files, directories, and PHP files
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d [OR]
RewriteCond %{REQUEST_FILENAME} \.php$ -f
RewriteRule ^ - [L]
EOF
  echo "[Hotfix] Injected PHP/dir allowlist rules."
fi

# 5) Remove any accidental local artifacts that should not be deployed
rm -f .htaccess.new .htaccess.tmp .htaccess.fixed .htaccess.working || true

# 6) Quick sanity checks
echo "[Hotfix] .htaccess head preview:"; head -n 25 .htaccess
echo "[Hotfix] .htaccess tail preview:"; tail -n 25 .htaccess

# 7) Deploy using existing deploy script
if [ -x scripts/deploy.sh ]; then
  echo "[Hotfix] Running deploy..."
  bash scripts/deploy.sh || true
else
  echo "[Hotfix] WARNING: scripts/deploy.sh not executable or missing."
fi

echo "[Hotfix] Done. Verify https://whimsicalfrog.us/ and /shop in a private window."
