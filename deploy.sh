#!/bin/bash

# Configuration
HOST="home419172903.1and1-data.host"
USER="acc899014616"
PASS="Palz2516!"
REMOTE_PATH="/"

# Clean up any stale git lock file
if [ -f .git/index.lock ]; then
  echo "Removing stale .git/index.lock file..."
  rm -f .git/index.lock
fi

# First, commit and push to GitHub if there are changes
if [ -n "$(git status --porcelain)" ]; then
  echo "Committing changes to GitHub..."
  git add .
  git commit -m "Auto-commit before deployment"
  git push
else
  echo "No changes to commit."
fi

# Create lftp commands
cat > deploy_commands.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
open sftp://$USER:$PASS@$HOST
mirror --reverse --delete --exclude-glob .git/ --exclude-glob node_modules/ --exclude-glob vendor/ --exclude-glob .vscode/ --exclude-glob *.log --exclude-glob *.sh --exclude-glob *.plist --exclude-glob temp_cron.txt --exclude-glob SERVER_MANAGEMENT.md --exclude-glob factory-tutorial/ --exclude-glob *.sql --include-glob credentials.json . $REMOTE_PATH
bye
EOL

# Run lftp with the commands
echo "Deploying to server..."
lftp -f deploy_commands.txt

# Clean up
echo "Cleaning up deploy_commands.txt..."
rm deploy_commands.txt

# Restart Node.js server
echo "Restarting Node.js server..."
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$HOST "cd $REMOTE_PATH && pm2 restart server.js || pm2 start server.js"

# Export local MySQL database (backup stored locally only)
echo "Exporting local MySQL database..."
mysqldump -u root -pPalz2516 whimsicalfrog > backup.sql

echo "Deployment completed!" 