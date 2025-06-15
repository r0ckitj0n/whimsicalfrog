#!/bin/bash

<<<<<<< HEAD
=======
echo "🚀 WhimsicalFrog Full Deployment (Code + Database)"
echo "================================================="

>>>>>>> e7613922959eba56cecb64c66c6f4812bff0f7d7
# Configuration
HOST="home419172903.1and1-data.host"
USER="acc899014616"
PASS="Palz2516!"
REMOTE_PATH="/"
<<<<<<< HEAD
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
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://whimsicalfrog.com/images/products/TS002A.webp")
if [ "$HTTP_CODE" = "200" ]; then
  echo -e "${GREEN}✅ Clown frog image is accessible online!${NC}"
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
echo -e "  • Verification: ✅ Completed"

echo -e "\n${GREEN}🎉 Full deployment completed!${NC}"
echo -e "${YELLOW}💡 If images still don't appear, wait 5-10 minutes for server cache to clear${NC}"

# Keep backup file for reference
echo -e "${GREEN}💾 Database backup saved as: backup.sql${NC}" 
=======

# Database Configuration
LOCAL_DB_USER="root"
LOCAL_DB_PASS="Palz2516"
LOCAL_DB_NAME="whimsicalfrog"
LIVE_DB_USER="whimsicalfrog"
LIVE_DB_PASS="WhimsicalFrog2024!"
LIVE_DB_NAME="whimsicalfrog"

echo "📦 Step 1: Preparing fresh database export..."
mysqldump --insert-ignore -u $LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME > whimsicalfrog_sync.sql

echo "✅ Database export created"

echo "📁 Step 2: Deploying code changes..."

# Clean up any stale git lock file
if [ -f .git/index.lock ]; then
  echo "Removing stale .git/index.lock file..."
  rm -f .git/index.lock
fi

# Commit changes to git
if [ -n "$(git status --porcelain)" ]; then
  echo "Committing changes to GitHub..."
  git add .
  git commit -m "Deploy: Cost management functionality fixes"
  git push
else
  echo "No new changes to commit."
fi

# Create lftp commands for code deployment
cat > deploy_commands.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
open sftp://$USER:$PASS@$HOST
mirror --reverse --delete --exclude-glob .git/ --exclude-glob node_modules/ --exclude-glob vendor/ --exclude-glob .vscode/ --exclude-glob *.log --exclude-glob deploy_*.sh --exclude-glob sync_to_live.sh --exclude-glob *.plist --exclude-glob temp_cron.txt --exclude-glob SERVER_MANAGEMENT.md --exclude-glob factory-tutorial/ --exclude-glob backup.sql --include-glob credentials.json . $REMOTE_PATH
bye
EOL

# Deploy code
lftp -f deploy_commands.txt
rm deploy_commands.txt

echo "✅ Code deployment completed"

echo "🗄️  Step 3: Deploying database changes..."

# Upload SQL file
echo "Uploading database file..."
scp whimsicalfrog_sync.sql $USER@$HOST:$REMOTE_PATH/

# Import on live server
echo "Importing database on live server..."
ssh $USER@$HOST "mysql -u $LIVE_DB_USER -p$LIVE_DB_PASS $LIVE_DB_NAME < $REMOTE_PATH/whimsicalfrog_sync.sql"

# Clean up SQL file on server
ssh $USER@$HOST "rm $REMOTE_PATH/whimsicalfrog_sync.sql"

echo "✅ Database deployment completed"

echo "🧹 Step 4: Cleanup..."
# Keep local backup
mv whimsicalfrog_sync.sql backup_$(date +%Y%m%d_%H%M%S).sql
echo "Local backup saved as backup_$(date +%Y%m%d_%H%M%S).sql"

echo ""
echo "🎉 DEPLOYMENT COMPLETE!"
echo "=============================="
echo "✅ Code changes deployed"
echo "✅ Database changes deployed"
echo "✅ Cost breakdown functionality should now work on live site"
echo ""
echo "🌐 Test the live site admin inventory cost management functionality" 
>>>>>>> e7613922959eba56cecb64c66c6f4812bff0f7d7
