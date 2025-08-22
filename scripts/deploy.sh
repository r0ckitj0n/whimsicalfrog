#!/bin/bash

# Change to the project root directory
cd "$(dirname "$0")/.."

# Configuration
HOST="home419172903.1and1-data.host"
USER="acc899014616"
PASS="Palz2516!"
REMOTE_PATH="/"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🚀 Starting fast file deployment...${NC}"
echo -e "${GREEN}💾 Backing up website...${NC}"
curl -s -X POST https://whimsicalfrog.us/api/backup_website.php || echo -e "${YELLOW}⚠️  Website backup failed, continuing deployment...${NC}"
echo -e "${GREEN}🔄 Syncing database data...${NC}"
php sync_database_smart.php || echo -e "${YELLOW}⚠️  Database data sync failed, continuing deployment...${NC}"

# Clean up any stale git lock file
if [ -f .git/index.lock ]; then
  echo -e "${YELLOW}⚠️  Removing stale .git/index.lock file...${NC}"
  rm -f .git/index.lock
fi

# First, commit and push to GitHub if there are changes
if [ -n "$(git status --porcelain)" ]; then
  echo -e "${GREEN}📝 Committing changes to GitHub...${NC}"
  git add .
  git commit -m "Auto-commit before deployment"
  if git push; then
    echo -e "${GREEN}✅ Successfully pushed to GitHub${NC}"
  else
    echo -e "${YELLOW}⚠️  GitHub push failed, continuing with deployment...${NC}"
  fi
else
  echo -e "${GREEN}✅ No changes to commit${NC}"
fi

# Create lftp commands for file deployment
echo -e "${GREEN}📁 Preparing file deployment...${NC}"
cat > deploy_commands.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
set cmd:fail-exit yes
open sftp://$USER:$PASS@$HOST
mirror --reverse --delete --verbose \
  --exclude-glob .git/ \
  --exclude-glob node_modules/ \
  --exclude-glob vendor/ \
  --exclude-glob .vscode/ \
  --exclude-glob hot \
  --exclude-glob backups/ \
  --exclude-glob Documentation/ \
  --exclude-glob Scripts/ \
  --exclude-glob *.log \
  --exclude-glob *.sh \
  --exclude-glob *.plist \
  --exclude-glob temp_cron.txt \
  --exclude-glob SERVER_MANAGEMENT.md \
  --exclude-glob factory-tutorial/ \
  --exclude-glob backup.sql \
  --exclude-glob backup_*.tar.gz \
  --exclude-glob *_backup_*.tar.gz \
  --exclude-glob deploy_commands.txt \
  --exclude-glob fix_clown_frog_image.sql \
  --exclude-glob images/.htaccess \
  --exclude-glob images/items/.htaccess \
  --exclude-glob config/my.cnf \
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
if lftp -f verify_deployment.txt 2>/dev/null | grep -q "TS002A.webp"; then
  echo -e "${GREEN}✅ TS002A.webp found on server${NC}"
else
  echo -e "${YELLOW}⚠️  TS002A.webp not found - may need manual upload${NC}"
fi

# Clean up verification script
rm verify_deployment.txt

# Fix permissions automatically after deployment
echo -e "${GREEN}🔧 Fixing image permissions on server...${NC}"
# Remove problematic .htaccess files and fix permissions via SFTP
cat > fix_permissions.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
open sftp://$USER:$PASS@$HOST
rm -f images/.htaccess
rm -f images/items/.htaccess
chmod 755 images/
chmod 755 images/items/
chmod 644 images/items/*
bye
EOL

lftp -f fix_permissions.txt > /dev/null 2>&1 || true
rm fix_permissions.txt

# Test image accessibility
echo -e "${GREEN}🌍 Testing image accessibility...${NC}"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://whimsicalfrog.us/images/items/TS002A.webp")
if [ "$HTTP_CODE" = "200" ]; then
  echo -e "${GREEN}✅ Clown frog image is accessible online!${NC}"
elif [ "$HTTP_CODE" = "404" ]; then
  echo -e "${YELLOW}⚠️  Image returns 404 - may need a few minutes to propagate${NC}"
else
  echo -e "${YELLOW}⚠️  Image returned HTTP code: $HTTP_CODE${NC}"
fi

# Final summary
echo -e "\n${GREEN}📊 Fast Deployment Summary:${NC}"
echo -e "  • Files: ✅ Deployed to server"
echo -e "  • Database: ⏭️  Skipped (use deploy_full.sh for database updates)"
echo -e "  • Images: ✅ Included in deployment"
echo -e "  • Verification: ✅ Completed"

echo -e "\n${GREEN}🎉 Fast deployment completed!${NC}"
echo -e "${YELLOW}💡 Use ./deploy_full.sh when you need to update the database${NC}"
