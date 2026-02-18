#!/usr/bin/env bash
set -euo pipefail

# Backup retention policy (safe by default).
# Default mode is dry-run; pass --apply to delete.
#
# Policy:
# - Keep all backup artifacts from the last DAILY_DAYS days.
# - For older artifacts, keep one artifact per week for WEEKLY_WEEKS.
# - Process only operational backup locations (not legacy/debug archives).

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BACKUPS_DIR="$ROOT_DIR/backups"
DAILY_DAYS="${WF_BACKUP_DAILY_DAYS:-7}"
WEEKLY_WEEKS="${WF_BACKUP_WEEKLY_WEEKS:-8}"
APPLY=0

usage() {
  cat <<'EOF'
Usage:
  scripts/maintenance/retain_backups.sh [--apply] [--daily-days N] [--weekly-weeks N]

Defaults:
  --daily-days 7
  --weekly-weeks 8

Notes:
  - Dry-run by default (prints planned deletions).
  - Only backup artifact files are considered (*.sql, *.sql.gz, *.tar, *.tar.gz, *.tgz, *.zip).
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --apply)
      APPLY=1
      shift
      ;;
    --daily-days)
      DAILY_DAYS="$2"
      shift 2
      ;;
    --weekly-weeks)
      WEEKLY_WEEKS="$2"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      usage
      exit 1
      ;;
  esac
done

if [[ ! -d "$BACKUPS_DIR" ]]; then
  echo "No backups directory found at $BACKUPS_DIR"
  exit 0
fi

if ! [[ "$DAILY_DAYS" =~ ^[0-9]+$ && "$WEEKLY_WEEKS" =~ ^[0-9]+$ ]]; then
  echo "daily-days and weekly-weeks must be non-negative integers" >&2
  exit 1
fi

now_epoch="$(date +%s)"

category_for_file() {
  local file_name="$1"
  case "$file_name" in
    *.sql|*.sql.gz) echo "db" ;;
    *.tar|*.tar.gz|*.tgz|*.zip) echo "files" ;;
    *) echo "other" ;;
  esac
}

collect_candidates() {
  find "$BACKUPS_DIR" -maxdepth 1 -type f \
    \( -name "*.sql" -o -name "*.sql.gz" -o -name "*.tar" -o -name "*.tar.gz" -o -name "*.tgz" -o -name "*.zip" \) -print
  for sub in live_sync local_pre_restore sql; do
    if [[ -d "$BACKUPS_DIR/$sub" ]]; then
      find "$BACKUPS_DIR/$sub" -maxdepth 1 -type f \
        \( -name "*.sql" -o -name "*.sql.gz" -o -name "*.tar" -o -name "*.tar.gz" -o -name "*.tgz" -o -name "*.zip" \) -print
    fi
  done
}

to_delete=()
while IFS= read -r f; do
  [[ -n "$f" ]] && to_delete+=("$f")
done < <(
  collect_candidates \
    | while IFS= read -r f; do
        [[ -f "$f" ]] || continue
        mtime="$(stat -f "%m" "$f")"
        base="$(basename "$f")"
        category="$(category_for_file "$base")"
        dir="$(dirname "$f")"
        printf "%s\t%s\t%s\t%s\n" "$mtime" "$f" "$category" "$dir"
      done \
    | sort -rn \
    | awk -F '\t' -v now="$now_epoch" -v daily="$DAILY_DAYS" -v weekly="$WEEKLY_WEEKS" '
      {
        mtime=$1
        path=$2
        category=$3
        dir=$4
        age_days=int((now-mtime)/86400)
        if (age_days <= daily) next

        cmd="date -r " mtime " +%G-%V"
        cmd | getline iso_week
        close(cmd)

        dir_key=dir "|" category
        week_key=dir_key "|" iso_week

        if (week_seen[week_key]) {
          print path
          next
        }
        if (week_count[dir_key] < weekly) {
          week_seen[week_key]=1
          week_count[dir_key]++
          next
        }
        print path
      }'
)

if [[ "${#to_delete[@]}" -eq 0 ]]; then
  echo "Backup retention: nothing to delete (daily=${DAILY_DAYS}, weekly=${WEEKLY_WEEKS})."
  exit 0
fi

echo "Backup retention candidates: ${#to_delete[@]} file(s)"
total_bytes=0
for f in "${to_delete[@]}"; do
  bytes="$(stat -f "%z" "$f" 2>/dev/null || echo 0)"
  total_bytes=$((total_bytes + bytes))
  echo "  - $f"
done

if [[ "$APPLY" -eq 0 ]]; then
  echo "Dry-run only. Re-run with --apply to delete."
  exit 0
fi

for f in "${to_delete[@]}"; do
  rm -f "$f"
done

echo "Deleted ${#to_delete[@]} file(s); reclaimed $((total_bytes / 1024 / 1024)) MB."
