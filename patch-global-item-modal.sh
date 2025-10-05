#!/usr/bin/env bash
set -euo pipefail

FILE="src/js/global-item-modal.js"
BAK="$FILE.bak.$(date +%s)"

if [[ ! -f "$FILE" ]]; then
  echo "[patch] Missing $FILE"
  exit 1
fi

cp "$FILE" "$BAK"
echo "[patch] Backup created: $BAK"

# 1) Upsert bindCloseHandlers(modal)
awk '
  BEGIN { RS = ORS = "\n"; found = 0 }
  /function[ \t]+bindCloseHandlers[ \t]*\(/ { found = 1 }
  { print }
  END {
    if (!found) {
      print "function bindCloseHandlers(modal) {"
      print "    const content