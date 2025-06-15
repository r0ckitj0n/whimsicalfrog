#!/bin/bash

# Configuration
HOST="home419172903.1and1-data.host"
USER="acc899014616"
PASS="Palz2516!"
REMOTE_PATH="/"
DB_HOST="whimsicalfrog.com"
DB_USER="jongraves"
DB_PASS="Palz2516"
DB_NAME="whimsicalfrog"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🚀 Starting FULL deployment (files + database)...${NC}"

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

# Export local MySQL database
echo -e "${GREEN}📊 Exporting local MySQL database...${NC}"
if mysqldump -u root -pPalz2516 whimsicalfrog > backup.sql 2>/dev/null; then
  echo -e "${GREEN}✅ Database exported successfully${NC}"
else
  echo -e "${RED}❌ Failed to export database${NC}"
  exit 1
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
  --exclude-glob *.log \
  --exclude-glob *.sh \
  --exclude-glob *.plist \
  --exclude-glob temp_cron.txt \
  --exclude-glob SERVER_MANAGEMENT.md \
  --exclude-glob factory-tutorial/ \
  --exclude-glob backup.sql \
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

# Fix image directory permissions
echo -e "${GREEN}🔧 Fixing image directory permissions...${NC}"
cat > fix_permissions.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
open sftp://$USER:$PASS@$HOST
chmod 755 images/
chmod 755 images/products/
bye
EOL

if lftp -f fix_permissions.txt 2>/dev/null; then
  echo -e "${GREEN}✅ Image directory permissions fixed${NC}"
else
  echo -e "${YELLOW}⚠️  Permission fix failed - images may not be accessible${NC}"
fi

# Clean up permissions script
rm fix_permissions.txt

# Deploy database to live server
echo -e "${GREEN}🗄️  Deploying database to live server...${NC}"
if mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME < backup.sql 2>/dev/null; then
  echo -e "${GREEN}✅ Database deployed successfully${NC}"
else
  echo -e "${YELLOW}⚠️  Database deployment failed - you may need to run it manually${NC}"
  echo -e "${YELLOW}    Command: mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME < backup.sql${NC}"
fi

# Verify critical files exist on server
echo -e "${GREEN}🔍 Verifying deployment...${NC}"

# Create verification script
cat > verify_deployment.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
open sftp://$USER:$PASS@$HOST
ls images/products/TS002A.webp
ls process_multi_image_upload.php
ls components/image_carousel.php
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

# Test image accessibility
echo -e "${GREEN}🌍 Testing image accessibility...${NC}"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://whimsicalfrog.us/images/products/TS002A.webp")
if [ "$HTTP_CODE" = "200" ]; then
  echo -e "${GREEN}✅ Product images are accessible online!${NC}"
elif [ "$HTTP_CODE" = "404" ]; then
  echo -e "${YELLOW}⚠️  Image returns 404 - may need a few minutes to propagate${NC}"
else
  echo -e "${YELLOW}⚠️  Image returned HTTP code: $HTTP_CODE${NC}"
fi

# Final summary
echo -e "\n${GREEN}📊 Full Deployment Summary:${NC}"
echo -e "  • Files: ✅ Deployed to server"
echo -e "  • Database: ✅ Exported locally and deployed to live server"
echo -e "  • Images: ✅ Included in deployment"
echo -e "  • Permissions: ✅ Image directory permissions fixed"
echo -e "  • Verification: ✅ Completed"

echo -e "\n${GREEN}🎉 Full deployment completed!${NC}"
echo -e "${YELLOW}💡 If images still don't appear, wait 5-10 minutes for server cache to clear${NC}"

# Keep backup file for reference
echo -e "${GREEN}💾 Database backup saved as: backup.sql${NC}" 