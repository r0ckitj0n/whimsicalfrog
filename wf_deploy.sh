#!/bin/bash
echo "🚀  WhimsicalFrog Full Deployment"
HOST="home419172903.1and1-data.host"
USER="acc899014616"
PASS="Palz2516!"
REMOTE_PATH="/"
# Public site URL used for importer and health checks
WEB_URL="https://whimsicalfrog.us"
DEPLOY_TOKEN="whfdeploytoken"
CHECK_IMAGE="/images/products/product_custom-tumbler-20oz.webp"
LOCAL_DB_USER="root"; LOCAL_DB_PASS="Palz2516"; LOCAL_DB_NAME="whimsicalfrog"

echo "📦 1. DB export …"
mysqldump --insert-ignore -u "$LOCAL_DB_USER" -p"$LOCAL_DB_PASS" "$LOCAL_DB_NAME" > whimsicalfrog_sync.sql || { echo "dump failed"; exit 1; }

echo "📁 2. Code sync …"
lftp -e "set sftp:auto-confirm yes; set ssl:verify-certificate no; \
         open sftp://$USER:$PASS@$HOST; \
         mirror -R --delete --exclude-glob .git/ --exclude-glob node_modules/ . $REMOTE_PATH; \
         chmod -R 755 images api; chmod 644 db_import_sql.php api/db_import_sql.php; bye" || { echo 'code sync failed'; exit 1; }

echo "🛠️ 2b. Syncing inventory images …"
php scripts/sync_inventory_images.php

echo "🗄️ 3. Uploading SQL dump …"
lftp -e "set sftp:auto-confirm yes; set ssl:verify-certificate no; open sftp://$USER:$PASS@$HOST; cd $REMOTE_PATH; put whimsicalfrog_sync.sql; bye" || { echo 'SQL upload failed'; exit 1; }

echo "   importing on server …"
IMPORT=$(curl -fs "$WEB_URL/api/sync_db.php?token=$DEPLOY_TOKEN") || { echo 'curl failed'; exit 1; }
echo "$IMPORT" | grep -q 'Import complete.' || { echo 'import failed'; exit 1; }

echo "✅ Database import completed"

echo "🔎 4. image check …"
curl -fs -o /dev/null "$WEB_URL$CHECK_IMAGE" || { echo 'image check failed'; exit 1; }

php scripts/add_fulfillment_notes_column.php

mv whimsicalfrog_sync.sql "backup_$(date +%Y%m%d_%H%M%S).sql"
echo "✅ deploy finished"
