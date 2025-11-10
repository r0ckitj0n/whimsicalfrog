#!/bin/bash

# Simple deployment script for WhimsicalFrog to SFTP server
# Uses the secure environment variables for credentials

echo "WhimsicalFrog Deployment to Production"
echo "======================================"

# Validate environment variables
if [[ -z "$SFTP_HOST" || -z "$SFTP_USER" || -z "$SFTP_PASSWORD" ]]; then
    echo "Error: SFTP credentials not found in environment variables"
    echo "Please ensure SFTP_HOST, SFTP_USER, and SFTP_PASSWORD are set"
    exit 1
fi

echo "Target: $SFTP_USER@$SFTP_HOST:${SFTP_PORT:-22}"
echo

# Build frontend assets FIRST
echo "Building frontend assets..."
npm run build
if [[ $? -ne 0 ]]; then
    echo "Error: Frontend build failed!"
    exit 1
fi

echo "Preparing incremental SFTP deployment (changed or missing files only)..."

# Ensure lftp is available
if ! command -v lftp >/dev/null 2>&1; then
    echo "Error: lftp is not installed or not on PATH."
    echo "Please install lftp (e.g., brew install lftp) and retry."
    exit 1
fi

# Remote configuration
PORT="${SFTP_PORT:-22}"
REMOTE_PATH="${SFTP_REMOTE_PATH:-/}"

echo "Using remote path: $REMOTE_PATH"

# Determine deletion behavior (ON by default)
DELETE_FLAG="--delete"
if [[ "${WF_DEPLOY_DELETE:-1}" = "0" ]]; then
  DELETE_FLAG=""
  echo "Remote delete disabled (WF_DEPLOY_DELETE=0). Remote-orphaned files will be kept."
else
  echo "Remote delete enabled (default). Set WF_DEPLOY_DELETE=0 to skip removing remote-orphaned files."
fi

# Build lftp mirror command file
cat > deploy_commands.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
set cmd:fail-exit yes
open sftp://$SFTP_USER:$SFTP_PASSWORD@$SFTP_HOST:$PORT
# Only upload changed or missing files:
#   --reverse   : local -> remote
#   --only-newer: skip if remote is same/newer mtime
#   --no-perms  : don't sync permissions
#   --delete    : optional; remove remote files that no longer exist locally (WF_DEPLOY_DELETE=1)
mirror --reverse $DELETE_FLAG --verbose --only-newer --no-perms \
  --exclude-glob .git/ \
  --exclude-glob node_modules/ \
  --exclude-glob vendor/ \
  --exclude-glob .vscode/ \
  --exclude-glob scripts/ \
  --exclude-glob tests/ \
  --exclude-glob backups/duplicates/** \
  --exclude-glob backups/tests/** \
  --exclude-glob documentation/ \
  --include-glob documentation/.htaccess \
  --exclude-glob *.log \
  --exclude-glob deploy_commands.txt \
  . $REMOTE_PATH
bye
EOL

echo "Deploying files to $SFTP_HOST ..."
if lftp -f deploy_commands.txt; then
  echo "✅ Deployment completed (incremental)."
  EXIT_CODE=0
else
  echo "❌ Deployment failed."
  EXIT_CODE=1
fi

# Clean up
rm -f deploy_commands.txt

exit $EXIT_CODE