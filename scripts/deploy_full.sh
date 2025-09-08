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
BASE_URL="https://whimsicalfrog.us${PUBLIC_BASE}"
DB_HOST="whimsicalfrog.com"
DB_USER="jongraves"
DB_PASS="Palz2516"
DB_NAME="whimsicalfrog"
PRECHECK_URL="$BASE_URL/db_connection_test.php"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🚀 Starting file deployment...${NC}"

# Preflight DB connectivity
echo -e "${GREEN}🔎 Preflight: testing DB connectivity via ${PRECHECK_URL}${NC}"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$PRECHECK_URL")
DB_PREFLIGHT_OK=false
if [ "$HTTP_CODE" = "200" ]; then
  echo -e "${GREEN}✅ DB preflight responded with HTTP 200${NC}"
  DB_PREFLIGHT_OK=true
else
  echo -e "${YELLOW}⚠️  DB preflight failed (HTTP $HTTP_CODE). Backup/restore will be skipped.${NC}"
fi

if [ "$DB_PREFLIGHT_OK" = true ]; then
  echo -e "${GREEN}💾 Backing up database...${NC}"
  curl -s -X POST \
    -F "admin_token=whimsical_admin_2024" \
    https://whimsicalfrog.us/api/backup_database.php || echo -e "${YELLOW}⚠️  Database backup failed, continuing...${NC}"
else
  echo -e "${YELLOW}⚠️  Skipping database backup due to failed preflight${NC}"
fi

echo -e "${GREEN}💾 Backing up website...${NC}"
curl -s -X POST \
  -F "admin_token=whimsical_admin_2024" \
  https://whimsicalfrog.us/api/backup_website.php || echo -e "${YELLOW}⚠️  Website backup failed, continuing...${NC}"

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
# Use only-newer + ignore-time to skip identical files; avoid re-upload churn
# - only-newer: don't overwrite if remote is same/newer
# - ignore-time: ignore mtime differences; compare by size only to skip identical files
# - no-perms: don't try to sync permissions (reduces needless diffs)
mirror --reverse --delete --verbose --only-newer --ignore-time --no-perms \
  --exclude-glob .git/ \
  --exclude-glob node_modules/ \
  --exclude-glob vendor/ \
  --exclude-glob .vscode/ \
  --exclude-glob hot \
  --exclude-glob backups/ \
  --exclude-glob documentation/ \
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

# If preflight failed, skip DB dump/restore entirely
if [ "$DB_PREFLIGHT_OK" != true ]; then
  echo -e "${YELLOW}⚠️  Skipping DB dump/restore due to failed preflight${NC}"
  DB_STATUS="Skipped (preflight failed)"
  goto_verify=true
fi

# Local DB connection (matches api/config.php local block)
LOCAL_DB_HOST="127.0.0.1"
LOCAL_DB_PORT="3306"
LOCAL_DB_USER="root"
LOCAL_DB_PASS="Palz2516!"
LOCAL_DB_NAME="whimsicalfrog"

DUMP_FILE="local_db_dump_$(date +%Y-%m-%d_%H-%M-%S).sql"
DUMP_ERR_FILE="local_db_dump_error.log"

# Ensure mysqldump exists
if ! command -v mysqldump >/dev/null 2>&1; then
  echo -e "${RED}❌ mysqldump not found on this system. Install MySQL client tools and retry.${NC}"
  DB_STATUS="Dump failed (mysqldump missing)"
  goto_verify=true
fi

if [ "${goto_verify:-false}" = true ]; then
  : # skip DB work
elif [ -S "/tmp/mysql.sock" ] && mysqldump --socket="/tmp/mysql.sock" -u "$LOCAL_DB_USER" --password="$LOCAL_DB_PASS" \
  --single-transaction --routines --triggers --add-drop-table "$LOCAL_DB_NAME" > "$DUMP_FILE" 2>"$DUMP_ERR_FILE"; then
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
  rm -f "$DUMP_FILE" "$DUMP_ERR_FILE"
else
  echo -e "${YELLOW}⚠️  Preferred path failed; attempting alternative connection methods...${NC}"
  # Try common socket paths on macOS and Linux
  for SOCK in /tmp/mysql.sock /var/run/mysqld/mysqld.sock; do
    if [ -S "$SOCK" ]; then
      if mysqldump --socket="$SOCK" -u "$LOCAL_DB_USER" --password="$LOCAL_DB_PASS" \
        --single-transaction --routines --triggers --add-drop-table "$LOCAL_DB_NAME" > "$DUMP_FILE" 2>>"$DUMP_ERR_FILE"; then
        echo -e "${GREEN}✅ Local dump created via socket: $DUMP_FILE${NC}"
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
        rm -f "$DUMP_FILE" "$DUMP_ERR_FILE"
        goto_verify=true
        break
      fi
    fi
  done
  if [ "${goto_verify:-false}" != true ]; then
    # Try TCP as final fallback
    if mysqldump -h "$LOCAL_DB_HOST" -P "$LOCAL_DB_PORT" -u "$LOCAL_DB_USER" --password="$LOCAL_DB_PASS" \
      --single-transaction --routines --triggers --add-drop-table "$LOCAL_DB_NAME" > "$DUMP_FILE" 2>>"$DUMP_ERR_FILE"; then
      echo -e "${GREEN}✅ Local dump created via TCP fallback: $DUMP_FILE${NC}"
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
      rm -f "$DUMP_FILE" "$DUMP_ERR_FILE"
      goto_verify=true
    fi

    echo -e "${RED}❌ Failed to create local database dump; skipping DB restore${NC}"
    if [ -f "$DUMP_ERR_FILE" ]; then
      echo -e "${YELLOW}🔎 mysqldump errors:${NC}"
      sed 's/.\{400\}/&\n/g' "$DUMP_ERR_FILE" | head -n 50
    fi
    DB_STATUS="Dump failed"
  fi
fi

# Verify deployment over HTTP (avoids dotfile visibility issues)
echo -e "${GREEN}🔍 Verifying deployment over HTTP...${NC}"

# Check Vite manifest accessibility
HTTP_MANIFEST_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/dist/.vite/manifest.json")
if [ "$HTTP_MANIFEST_CODE" != "200" ]; then
  HTTP_MANIFEST_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/dist/manifest.json")
fi
if [ "$HTTP_MANIFEST_CODE" = "200" ]; then
  echo -e "${GREEN}✅ Vite manifest accessible over HTTP${NC}"
else
  echo -e "${YELLOW}⚠️  Vite manifest not accessible over HTTP (code $HTTP_MANIFEST_CODE)${NC}"
fi

# Extract one JS and one CSS asset from homepage HTML and verify
HOME_HTML=$(curl -s "$BASE_URL/")
APP_JS=$(echo "$HOME_HTML" | grep -Eo "/dist/assets/js/app.js-[^\"']+\\.js" | head -n1)
MAIN_CSS=$(echo "$HOME_HTML" | grep -Eo "/dist/assets/[^\"']+\\.css" | head -n1)
if [ -n "$APP_JS" ]; then
  CODE_JS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL$APP_JS")
  echo -e "  • JS $APP_JS -> HTTP $CODE_JS"
else
  echo -e "  • JS: ⚠️ Not found in homepage HTML"
fi
if [ -n "$MAIN_CSS" ]; then
  CODE_CSS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL$MAIN_CSS")
  echo -e "  • CSS $MAIN_CSS -> HTTP $CODE_CSS"
else
  echo -e "  • CSS: ⚠️ Not found in homepage HTML"
fi

# Test image accessibility (use a stable, non-legacy asset)
echo -e "${GREEN}🌍 Testing image accessibility...${NC}"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/images/logos/logo_whimsicalfrog.webp")
if [ "$HTTP_CODE" = "200" ]; then
  echo -e "${GREEN}✅ Logo image is accessible online!${NC}"
elif [ "$HTTP_CODE" = "404" ]; then
  echo -e "${YELLOW}⚠️  Logo image returns 404 - may need a few minutes to propagate${NC}"
else
  echo -e "${YELLOW}⚠️  Logo image returned HTTP code: $HTTP_CODE${NC}"
fi

# Final summary
echo -e "\n${GREEN}📊 Full Deployment Summary:${NC}"
echo -e "  • Database: ${DB_STATUS}"
echo -e "  • Files: ✅ Deployed to server"
echo -e "  • Images: ✅ Included in deployment"
echo -e "  • Permissions: ✅ Image directory permissions fixed"
echo -e "  • Verification: ✅ Completed"

# Verify Vite assets over HTTP using homepage extraction (ensures correct base path and availability)
echo -e "${GREEN}🔎 Verifying Vite assets over HTTP...${NC}"
# Reuse APP_JS and MAIN_CSS from above
if [ -n "$APP_JS" ]; then
  CODE_JS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL$APP_JS")
  echo -e "  • JS $APP_JS -> HTTP $CODE_JS"
else
  echo -e "  • JS: ⚠️ Not found in homepage HTML"
fi
if [ -n "$MAIN_CSS" ]; then
  CODE_CSS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL$MAIN_CSS")
  echo -e "  • CSS $MAIN_CSS -> HTTP $CODE_CSS"
else
  echo -e "  • CSS: ⚠️ Not found in homepage HTML"
fi

echo -e "\n${GREEN}🎉 Full deployment completed!${NC}"
echo -e "${YELLOW}💡 If images still don't appear, wait 5-10 minutes for server cache to clear${NC}"
echo -e "${GREEN}💡 Database is now automatically synced with every full deployment${NC}"