#!/bin/bash

# Configuration
HOST="home419172903.1and1-data.host"
USER="acc899014616"
PASS="Palz2516!"
REMOTE_PATH="/"

# First, commit and push to GitHub
echo "Committing changes to GitHub..."
git add .
git commit -m "Auto-commit before deployment"
git push

# Create lftp commands
cat > deploy_commands.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
open sftp://$USER:$PASS@$HOST
mirror --reverse --delete --exclude-glob .git/ --exclude-glob node_modules/ --exclude-glob vendor/ --exclude-glob .vscode/ --exclude-glob *.log --exclude-glob *.sh --exclude-glob *.plist --exclude-glob temp_cron.txt --exclude-glob SERVER_MANAGEMENT.md --exclude-glob factory-tutorial/ --include-glob credentials.json . $REMOTE_PATH
bye
EOL

# Run lftp with the commands
echo "Deploying to server..."
lftp -f deploy_commands.txt

# Clean up
rm deploy_commands.txt

echo "Deployment completed!" 