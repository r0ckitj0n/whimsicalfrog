#!/usr/bin/env bash
# Restore live whimsicalfrog database into local instance
# Requires sshpass (install via brew install hudochenkov/sshpass/sshpass)
set -euo pipefail

# SSH connection details
tunnel_port=3307
ssh_user="u69291289"
ssh_host="home419172903.1and1-data.host"
ssh_port=22
ssh_pass="Palz2516!"

# Remote database details
remote_db_host="db5017975223.hosting-data.io"
remote_db_port=3306
remote_db="dbs14295502"
remote_user="dbu2826619"
remote_pass="Palz2516!"

dump_file="live_backup.sql"

# Local database details
local_db="whimsicalfrog"
local_user="admin"
local_pass="Palz2516!"

# Check sshpass
if ! command -v sshpass &> /dev/null; then
  echo "ERROR: sshpass not found. Install with 'brew install hudochenkov/sshpass/sshpass'."
  exit 1
fi

echo "→ Dumping remote database via SSH to $dump_file..."
sshpass -p "$ssh_pass" ssh -o StrictHostKeyChecking=no -p $ssh_port ${ssh_user}@${ssh_host} \
  "mysqldump --single-transaction --skip-lock-tables --no-tablespaces -h${remote_db_host} -P${remote_db_port} -u${remote_user} -p'${remote_pass}' ${remote_db}" > ${dump_file}

echo "→ Dropping and recreating local database '$local_db'..."
mysql -uroot -h127.0.0.1 -e "DROP DATABASE IF EXISTS ${local_db}; CREATE DATABASE ${local_db};"

echo "→ Importing dump into local '$local_db'..."
mysql -h127.0.0.1 -uroot ${local_db} < ${dump_file}


echo " Restoration complete. Local DB is now in sync with live."
