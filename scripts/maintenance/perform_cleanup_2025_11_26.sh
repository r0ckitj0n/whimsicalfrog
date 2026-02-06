#!/bin/bash

# Define backup root
BACKUP_DIR="backups/cleanup_2025_11_26"
mkdir -p "$BACKUP_DIR/api"
mkdir -p "$BACKUP_DIR/admin"
mkdir -p "$BACKUP_DIR/templates"
mkdir -p "$BACKUP_DIR/scripts"
mkdir -p "$BACKUP_DIR/setup"

# Move Code Artifacts
echo "Moving code artifacts..."
mv api/start_over.php "$BACKUP_DIR/api/" 2>/dev/null
mv api/migrate_room_numbering.php "$BACKUP_DIR/api/" 2>/dev/null
mv api/convert_to_centralized_db.php "$BACKUP_DIR/api/" 2>/dev/null
mv api/sync_db.php "$BACKUP_DIR/api/" 2>/dev/null
mv admin/docs.php "$BACKUP_DIR/admin/" 2>/dev/null
mv templates/wf-starter "$BACKUP_DIR/templates/" 2>/dev/null

# Move Documentation
echo "Moving documentation..."
mv documentation "$BACKUP_DIR/" 2>/dev/null
mv README.md "$BACKUP_DIR/" 2>/dev/null
mv cleanup_plan.md "$BACKUP_DIR/" 2>/dev/null
mv handoff_plan.md "$BACKUP_DIR/" 2>/dev/null
mv scripts/README_TOOLTIPS.md "$BACKUP_DIR/scripts/" 2>/dev/null
mv setup/PROJECT_GUARDRAILS_STARTER.md "$BACKUP_DIR/setup/" 2>/dev/null

# Cleanup Stale Files
echo "Removing stale files..."
rm -f hot

# Create Placeholder README
echo "# WhimsicalFrog

Documentation and legacy code were archived to \`$BACKUP_DIR\` on $(date).
" > README.md

echo "Cleanup complete."
