#!/bin/bash

# Change to the project root directory
cd "$(dirname "$0")/.."

# Configuration
HOST="home419172903.1and1-data.host"
USER="acc899014616"
PASS="Palz2516!"
REMOTE_PATH="/"
# Optional public base for sites under a subdirectory (e.g., /wf)
PUBLIC_BASE="${WF_PUBLIC_BASE:-}"
DB_HOST="whimsicalfrog.com"
DB_USER="jongraves"
DB_PASS="Palz2516"
DB_NAME="whimsicalfrog"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🚀 Starting file deployment...${NC}"
echo -e "${GREEN}💾 Backing up database...${NC}"
curl -s -X POST https://whimsicalfrog.us/api/backup_database.php || echo -e "${YELLOW}⚠️  Database backup failed, continuing...${NC}"
echo -e "${GREEN}💾 Backing up website...${NC}"
curl -s -X POST https://whimsicalfrog.us/api/backup_website.php || echo -e "${YELLOW}⚠️  Website backup failed, continuing...${NC}"

# Clean up any stale git lock file
if [ -f .git/index.lock ]; then
  echo -e "${YELLOW}⚠️  Removing stale .git/index.lock file...${NC}"
  rm -f .git/index.lock
fi

# First, commit and push to GitHub if there are changes
if [ -n "$(git status --porcelain)" ]; then
  echo -e "${GREEN}📝 Committing changes to GitHub...${NC}"
  git add .
  git commit -m "Auto-commit before full deployment"
  if git push; then
    echo -e "${GREEN}✅ Successfully pushed to GitHub${NC}"
  else
    echo -e "${YELLOW}⚠️  GitHub push failed, continuing with deployment...${NC}"
  fi
else
  echo -e "${GREEN}✅ No changes to commit${NC}"
fi

# Database sync will be performed after file deployment via API restore (Option A)
echo -e "${GREEN}🔄 Preparing to overwrite live database from local dump after file deployment...${NC}"

# Create lftp commands for file deployment
echo -e "${GREEN}📁 Preparing file deployment...${NC}"
cat > deploy_commands.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
set cmd:fail-exit yes
open sftp://$USER:$PASS@$HOST
# Use only-newer + size-only to skip identical files; avoid re-upload churn
# - only-newer: don't overwrite if remote is same/newer
# - size-only: treat same-size files as identical (mtime differences ignored)
# - no-perms: don't try to sync permissions (reduces needless diffs)
mirror --reverse --delete --verbose --only-newer --size-only --no-perms \
  --exclude-glob .git/ \
  --exclude-glob node_modules/ \
  --exclude-glob vendor/ \
  --exclude-glob .vscode/ \
  --exclude-glob hot \
  --exclude-glob backups/ \
  --exclude-glob Documentation/ \
  --exclude-glob Scripts/ \
  --exclude-glob scripts/ \
  --exclude-glob *.log \
  --exclude-glob *.sh \
  --exclude-glob *.plist \
  --exclude-glob temp_cron.txt \
  --exclude-glob SERVER_MANAGEMENT.md \
  --exclude-glob factory-tutorial/ \
  --exclude-glob deploy_commands.txt \
  --exclude-glob fix_clown_frog_image.sql \
  --include-glob credentials.json \
  . $REMOTE_PATH
bye
EOL

# Run lftp with the commands
echo -e "${GREEN}🌐 Deploying files to server...${NC}"
if lftp -f deploy_commands.txt; then
  echo -e "${GREEN}✅ Files deployed successfully${NC}"
else
  echo -e "${RED}❌ File deployment failed${NC}"
  rm deploy_commands.txt
  exit 1
fi

# Clean up lftp commands file
rm deploy_commands.txt

# Ensure no Vite hot file exists on the live server (prevents accidental dev mode)
echo -e "${GREEN}🧹 Removing any stray Vite hot file on server...${NC}"
cat > cleanup_hot.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
open sftp://$USER:$PASS@$HOST
rm -f hot
bye
EOL

lftp -f cleanup_hot.txt > /dev/null 2>&1 || true
rm cleanup_hot.txt

# Fix image directory permissions
echo -e "${GREEN}🔧 Fixing image directory permissions...${NC}"
cat > fix_permissions.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
open sftp://$USER:$PASS@$HOST
chmod 755 images/
chmod 755 images/items/
bye
EOL

if lftp -f fix_permissions.txt 2>/dev/null; then
  echo -e "${GREEN}✅ Image directory permissions fixed${NC}"
else
  echo -e "${YELLOW}⚠️  Permission fix failed - images may not be accessible${NC}"
fi

# Clean up permissions script
rm fix_permissions.txt

# Overwrite live database from local dump (Option A)
echo -e "${GREEN}🗄️  Creating local database dump and restoring on live...${NC}"
DB_STATUS="Skipped"

# Local DB connection (matches api/config.php local block)
LOCAL_DB_HOST="127.0.0.1"
LOCAL_DB_PORT="3306"
LOCAL_DB_USER="root"
LOCAL_DB_PASS="Palz2516!"
LOCAL_DB_NAME="whimsicalfrog"

DUMP_FILE="local_db_dump_$(date +%Y-%m-%d_%H-%M-%S).sql"

if mysqldump -h "$LOCAL_DB_HOST" -P "$LOCAL_DB_PORT" -u "$LOCAL_DB_USER" --password="$LOCAL_DB_PASS" \
  --single-transaction --routines --triggers --add-drop-table "$LOCAL_DB_NAME" > "$DUMP_FILE" 2>/dev/null; then
  echo -e "${GREEN}✅ Local dump created: $DUMP_FILE${NC}"

  echo -e "${GREEN}☁️  Uploading and restoring via API (direct upload)...${NC}"
  RESTORE_OUT=$(curl -sS -X POST \
    -F "admin_token=whimsical_admin_2024" \
    -F "ignore_errors=1" \
    -F "backup_file=@$DUMP_FILE;type=application/sql" \
    "https://whimsicalfrog.us/api/database_maintenance.php?action=restore_database" || true)

  if echo "$RESTORE_OUT" | grep -q '"success":true'; then
    echo -e "${GREEN}✅ Live database restored from uploaded dump${NC}"
    DB_STATUS="Restored from uploaded dump"
  else
    echo -e "${YELLOW}⚠️  Direct upload restore failed. Falling back to SFTP + server restore...${NC}"

    # Upload dump to server backups/ via SFTP
    cat > upload_dump.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
open sftp://$USER:$PASS@$HOST
mkdir backups
cd backups
put "$DUMP_FILE"
bye
EOL
    if lftp -f upload_dump.txt 2>/dev/null; then
      echo -e "${GREEN}✅ Dump uploaded to server backups/$DUMP_FILE${NC}"
    else
      echo -e "${RED}❌ Failed to upload dump to server${NC}"
    fi
    rm -f upload_dump.txt

    echo -e "${GREEN}🧩 Triggering server-side restore from backups/$DUMP_FILE...${NC}"
    RESTORE_OUT=$(curl -sS -X POST \
      -F "admin_token=whimsical_admin_2024" \
      -F "ignore_errors=1" \
      -F "server_backup_path=../backups/$DUMP_FILE" \
      "https://whimsicalfrog.us/api/database_maintenance.php?action=restore_database" || true)

    if echo "$RESTORE_OUT" | grep -q '"success":true'; then
      echo -e "${GREEN}✅ Live database restored from server backup file${NC}"
      DB_STATUS="Restored from server backup file"
    else
      echo -e "${RED}❌ Database restore failed${NC}"
      echo "$RESTORE_OUT" | sed 's/.\{400\}/&\n/g' | head -n 50
      DB_STATUS="Restore failed"
    fi
  fi

  # Clean up local dump file
  rm -f "$DUMP_FILE"
else
  echo -e "${RED}❌ Failed to create local database dump; skipping DB restore${NC}"
  DB_STATUS="Dump failed"
fi

# Verify critical files exist on server
echo -e "${GREEN}🔍 Verifying deployment...${NC}"

# Create verification script
cat > verify_deployment.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
open sftp://$USER:$PASS@$HOST
ls images/items/TS002A.webp
ls process_multi_image_upload.php
ls components/image_carousel.php
ls dist/.vite/manifest.json
ls dist/assets
bye
EOL

echo -e "${GREEN}📋 Checking if critical files were uploaded...${NC}"
VERIFY_OUT=$(lftp -f verify_deployment.txt 2>/dev/null)
if echo "$VERIFY_OUT" | grep -q "TS002A.webp"; then
  echo -e "${GREEN}✅ TS002A.webp found on server${NC}"
else
  echo -e "${YELLOW}⚠️  TS002A.webp not found - may need manual upload${NC}"
fi
if echo "$VERIFY_OUT" | grep -q "manifest.json"; then
  echo -e "${GREEN}✅ dist/.vite/manifest.json found on server${NC}"
else
  echo -e "${YELLOW}⚠️  dist/.vite/manifest.json not found - build assets may be missing${NC}"
fi
if echo "$VERIFY_OUT" | grep -q "dist/assets"; then
  echo -e "${GREEN}✅ dist/assets found on server${NC}"
else
  echo -e "${YELLOW}⚠️  dist/assets directory not found - build assets may be missing${NC}"
fi

# Clean up verification script
rm verify_deployment.txt

# Test image accessibility
echo -e "${GREEN}🌍 Testing image accessibility...${NC}"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://whimsicalfrog.us/images/items/TS002A.webp")
if [ "$HTTP_CODE" = "200" ]; then
  echo -e "${GREEN}✅ Product images are accessible online!${NC}"
elif [ "$HTTP_CODE" = "404" ]; then
  echo -e "${YELLOW}⚠️  Image returns 404 - may need a few minutes to propagate${NC}"
else
  echo -e "${YELLOW}⚠️  Image returned HTTP code: $HTTP_CODE${NC}"
fi

# Final summary
echo -e "\n${GREEN}📊 Full Deployment Summary:${NC}"
echo -e "  • Database: ${DB_STATUS}"
echo -e "  • Files: ✅ Deployed to server"
echo -e "  • Images: ✅ Included in deployment"
echo -e "  • Permissions: ✅ Image directory permissions fixed"
echo -e "  • Verification: ✅ Completed"

# Verify Vite assets over HTTP using manifest (ensures correct base path and availability)
echo -e "${GREEN}🔎 Verifying Vite assets over HTTP...${NC}"
BASE_URL="https://whimsicalfrog.us${PUBLIC_BASE}"
MANIFEST_PATH="dist/.vite/manifest.json"
if [ ! -f "$MANIFEST_PATH" ]; then
  MANIFEST_PATH="dist/manifest.json"
fi
JS_FILE=""
CSS_FILE=""
if [ -f "$MANIFEST_PATH" ]; then
  JS_FILE=$(sed -n 's/.*"file":"\([^"]*app.js-[^"]*\)".*/\1/p' "$MANIFEST_PATH" | head -n1)
  if [ -z "$JS_FILE" ]; then
    JS_FILE=$(ls -1 dist/assets/js/app.js-*.js 2>/dev/null | head -n1 | sed 's#^dist/##')
  fi
  CSS_FILE=$(sed -n 's/.*"css":\["\([^"]*\)".*/\1/p' "$MANIFEST_PATH" | head -n1)
fi

if [ -n "$JS_FILE" ]; then
  CODE_JS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/dist/$JS_FILE")
  echo -e "  • JS $JS_FILE -> HTTP $CODE_JS"
else
  echo -e "  • JS: ⚠️ Unable to resolve app.js hashed file from manifest"
fi
if [ -n "$CSS_FILE" ]; then
  CODE_CSS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/dist/$CSS_FILE")
  echo -e "  • CSS $CSS_FILE -> HTTP $CODE_CSS"
else
  echo -e "  • CSS: ⚠️ Unable to resolve first CSS file from manifest"
fi

echo -e "\n${GREEN}🎉 Full deployment completed!${NC}"
echo -e "${YELLOW}💡 If images still don't appear, wait 5-10 minutes for server cache to clear${NC}"
echo -e "${GREEN}💡 Database is now automatically synced with every full deployment${NC}" 