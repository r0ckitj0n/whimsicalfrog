#!/bin/bash

echo "🚀 WhimsicalFrog Full Deployment (Code + Database)"
echo "================================================="

# Configuration
HOST="home419172903.1and1-data.host"
USER="acc899014616"
PASS="Palz2516!"
REMOTE_PATH="/"

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