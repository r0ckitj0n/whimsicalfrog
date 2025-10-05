#!/usr/bin/env bash
set -euo pipefail

DATE="${1:-2025-09-02}"
ARCHIVE_DIR="backups/duplicates_${DATE}"
REPORT_DIR="reports/deletion-audit/${DATE}"

mkdir -p "$REPORT_DIR"
SUMMARY="$REPORT_DIR/summary.txt"
DETAILS="$REPORT_DIR/details.tsv"
: > "$SUMMARY"
: > "$DETAILS"

echo "Duplicate Deletion Audit Report" >> "$SUMMARY"
echo "Date: ${DATE}" >> "$SUMMARY"
echo "Archive dir: $ARCHIVE_DIR" >> "$SUMMARY"

if [ ! -d "$ARCHIVE_DIR" ]; then
  echo "\nArchive directory not found: $ARCHIVE_DIR" | tee -a "$SUMMARY"
  exit 0
fi

# List files
FILE_LIST=$(find "$ARCHIVE_DIR" -type f | sort || true)
COUNT=$(printf "%s\n" "$FILE_LIST" | sed '/^$/d' | wc -l | tr -d ' ')

echo -e "\nTotal archived files: $COUNT" >> "$SUMMARY"
echo -e "\nFiles:" >> "$SUMMARY"
printf "%s\n" "$FILE_LIST" >> "$SUMMARY"

echo -e "status\tarchived_path\tbasename\treference_count" >> "$DETAILS"

# Search for references by basename across repo excluding backup/vendor/node_modules/dist/.git
IFS=$'\n'
for f in $FILE_LIST; do
  [ -z "$f" ] && continue
  base=$(basename "$f")
  patt=$(printf "%s" "$base" | sed 's/[.[\*^$]/\\&/g')
  # Grep outputs every match line; count lines
  matches=$( (grep -RIn --exclude-dir=.git --exclude-dir=backups --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=dist --exclude-dir=.cache --exclude-dir=reports -- "$patt" . 2>/dev/null || true) | wc -l | tr -d ' ' )
  status="unused"
  if [ "${matches:-0}" -gt 0 ]; then status="referenced"; fi
  printf "%s\t%s\t%s\t%s\n" "$status" "$f" "$base" "$matches" >> "$DETAILS"
done

# Append quick stats to summary
used=$(awk -F"\t" 'NR>1 && $1=="referenced" {c++} END{print c+0}' "$DETAILS")
unused=$(awk -F"\t" 'NR>1 && $1=="unused" {c++} END{print c+0}' "$DETAILS")

echo -e "\nReferenced count: $used" >> "$SUMMARY"
echo "Unused count: $unused" >> "$SUMMARY"

echo "Report written to: $SUMMARY and $DETAILS"
