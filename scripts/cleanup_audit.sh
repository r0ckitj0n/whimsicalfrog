#!/bin/bash
# cleanup_audit.sh
# ----------------------------------------
# Performs the approved cleanup tasks:
#   A. Remove macOS .DS_Store and other OS cruft
#   B. Move stray log files into logs/
#   C. Remove empty directories (excluding .git)
#   D. Delete obsolete *.txt reports and backups
#
# Run from project root:
#   bash scripts/cleanup_audit.sh
# ----------------------------------------
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
LOG_DIR="$PROJECT_ROOT/logs"

cd "$PROJECT_ROOT"

echo "[cleanup] Starting cleanup at $(date '+%Y-%m-%d %H:%M:%S')"

# A. Remove .DS_Store files
DS_COUNT=$(find . -name ".DS_Store" | wc -l | tr -d ' ')
if [ "$DS_COUNT" -gt 0 ]; then
  echo "[cleanup] Removing $DS_COUNT .DS_Store files..."
  find . -name ".DS_Store" -delete
else
  echo "[cleanup] No .DS_Store files found."
fi

# B. Move stray logs to logs/
mkdir -p "$LOG_DIR"

move_log_if_exists() {
  local src="$1" dst_name="$2"
  if [ -f "$src" ]; then
    echo "[cleanup] Moving $src to $LOG_DIR/$dst_name"
    mv "$src" "$LOG_DIR/$dst_name"
  fi
}

move_log_if_exists "monitor.log" "monitor_root.log"
move_log_if_exists "scripts/php_server.log" "php_server_script.log"

# Historic backups logs -> delete (assumed obsolete after audit)
if [ -d "backups" ]; then
  find backups -maxdepth 1 -type f -name "*.log" -print -delete
fi

# C. Remove empty directories (excluding .git*)
EMPTY_DIRS=$(find . -type d -empty -not -path "./.git*" -not -path "." )
if [ -n "$EMPTY_DIRS" ]; then
  echo "$EMPTY_DIRS" | while read -r d; do
    echo "[cleanup] Removing empty dir $d";
    rmdir "$d" || true;
  done
else
  echo "[cleanup] No empty directories to remove."
fi

# D. Delete obsolete txt reports (excluding robots.txt)
TXT_COUNT=$(find txt -type f -name "*.txt" 2>/dev/null | wc -l | tr -d ' ')
if [ "$TXT_COUNT" -gt 0 ]; then
  echo "[cleanup] Deleting $TXT_COUNT obsolete txt report files in txt/"
  find txt -type f -name "*.txt" -delete
fi

# Delete .txt files in backups (assumed obsolete)
BACKUP_TXT_COUNT=$(find backups -type f -name "*.txt" 2>/dev/null | wc -l | tr -d ' ')
if [ "$BACKUP_TXT_COUNT" -gt 0 ]; then
  echo "[cleanup] Deleting $BACKUP_TXT_COUNT obsolete txt files in backups/"
  find backups -type f -name "*.txt" -delete
fi

echo "[cleanup] Cleanup completed at $(date '+%Y-%m-%d %H:%M:%S')"
