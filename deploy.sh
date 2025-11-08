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

echo "Preparing deployment package..."

# Create temporary deployment directory
DEPLOY_DIR=$(mktemp -d)
echo "Temporary directory: $DEPLOY_DIR"

# Copy core files (excluding development-only files)
RSYNC_EXCLUDES=(
    "--exclude=logs/"
    "--exclude=database/"
    "--exclude=.env"
    "--exclude=setup-dev-database.php"
    "--exclude=database-config-dev.php"
    "--exclude=deploy.sh"
    "--exclude=node_modules/"
    "--exclude=backups/"
    "--exclude=tests/"
    "--exclude=.git/"
)

if [[ "${WF_DEPLOY_EXCLUDE_TMP:-1}" != "0" ]]; then
    RSYNC_EXCLUDES+=("--exclude=tmp/" "--exclude=/tmp/")
fi

rsync -av "${RSYNC_EXCLUDES[@]}" ./ "$DEPLOY_DIR/"

echo "Files prepared for deployment:"
find "$DEPLOY_DIR" -type f | head -10
echo "... and more"

echo
echo "Deployment package ready in: $DEPLOY_DIR"
echo
echo "SECURITY NOTE: For production deployment, use secure methods:"
echo "1. Use SSH key-based authentication instead of passwords"
echo "2. Use rsync with SSH: rsync -avz --delete $DEPLOY_DIR/ user@host:/web/path/"
echo "3. Or use secure SFTP clients with proper authentication"
echo
echo "Manual deployment with your SFTP credentials:"
echo "  Host: $SFTP_HOST"
echo "  Port: ${SFTP_PORT:-22}"
echo "  User: $SFTP_USER"
echo "  (Use your secure SFTP client with the provided password)"
echo
echo "Post-deployment checklist:"
echo "- Set WHF_ENV=production on production server"
echo "- Configure MySQL database credentials"
echo "- Configure your web server (Apache/Nginx) if needed"
echo "- Test all functionality thoroughly"

# Cleanup option
read -p "Remove temporary directory? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    rm -rf "$DEPLOY_DIR"
    echo "Temporary directory removed"
fi