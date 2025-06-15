#!/bin/bash

# Local MySQL credentials
LOCAL_DB_USER="root"
LOCAL_DB_PASS="Palz2516"
LOCAL_DB_NAME="whimsicalfrog"

# Live server details (IONOS)
LIVE_USER="acc899014616"
LIVE_HOST="home419172903.1and1-data.host"
LIVE_PATH="/path/to/your/live/site" # <-- Update this to your actual live site path
LIVE_DB_USER="whimsicalfrog"
LIVE_DB_PASS="WhimsicalFrog2024!"
LIVE_DB_NAME="whimsicalfrog"

# Export local DB with --insert-ignore to avoid duplicates
mysqldump --insert-ignore -u $LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME > whimsicalfrog_sync.sql

# Rsync images (only new/changed files)
rsync -av --ignore-existing images/ $LIVE_USER@$LIVE_HOST:$LIVE_PATH/images/

# Upload SQL file
scp whimsicalfrog_sync.sql $LIVE_USER@$LIVE_HOST:$LIVE_PATH/

# Import on live server (run this via SSH)
ssh $LIVE_USER@$LIVE_HOST "mysql -u $LIVE_DB_USER -p$LIVE_DB_PASS $LIVE_DB_NAME < $LIVE_PATH/whimsicalfrog_sync.sql"

echo "Sync complete!" 